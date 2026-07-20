<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_return_restocks_refunds_and_claws_back_points(): void
    {
        config(['billing.tax_percent' => 10]);

        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $customer = Customer::create([
            'name' => 'Kamala Devi',
            'phone' => '0771234567',
        ]);
        $product = Product::create([
            'name' => 'Rice 5kg',
            'sku' => 'SKU-RICE-5',
            'barcode' => 'BC-RICE-5',
            'cost_price' => 900,
            'selling_price' => 1000,
            'stock_qty' => 50,
            'reorder_level' => 5,
        ]);

        // Sell 10 units at Rs 1000 with a 10% discount and (test-only) 10%
        // tax, to a customer, so discount/tax/points ratios are all non-zero
        // and exercise the proportional refund math below.
        $this->actingAs($cashier)->post(route('cashier.billing.store'), [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10],
            ],
            'discount_percent' => 10,
            'points_to_redeem' => 0,
            'payment_method' => 'cash',
        ])->assertRedirect();

        $sale = Sale::firstOrFail();
        $saleItem = SaleItem::where('sale_id', $sale->id)->firstOrFail();

        $this->assertEquals(10000.0, (float) $sale->subtotal);
        $this->assertEquals(1000.0, (float) $sale->discount);
        $this->assertEquals(900.0, (float) $sale->tax);
        $this->assertEquals(9900.0, (float) $sale->total);
        $this->assertEquals(99, $sale->points_earned);

        $customer->refresh();
        $this->assertEquals(99, $customer->points_balance);

        $stockAfterSale = $product->fresh()->stock_qty;

        // Return exactly half the units sold (5 of 10).
        $this->actingAs($cashier)->post(route('returns.store'), [
            'sale_id' => $sale->id,
            'items' => [
                ['sale_item_id' => $saleItem->id, 'quantity' => 5],
            ],
            'refund_method' => 'cash',
            'reason' => 'Customer changed their mind',
        ])->assertRedirect();

        $saleReturn = SaleReturn::firstOrFail();

        $this->assertEquals($stockAfterSale + 5, $product->fresh()->stock_qty);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 5,
            'reason' => 'return',
        ]);

        // Exactly half the line by value, so refunds are exactly half of
        // the original discount/tax/total.
        $this->assertEquals(5000.0, (float) $saleReturn->subtotal_refunded);
        $this->assertEquals(500.0, (float) $saleReturn->discount_refunded);
        $this->assertEquals(450.0, (float) $saleReturn->tax_refunded);
        $this->assertEquals(4950.0, (float) $saleReturn->total_refunded);

        $this->assertEquals(49, $saleReturn->points_clawed_back);

        $customer->refresh();
        $this->assertEquals(50, $customer->points_balance);

        $this->assertDatabaseHas('loyalty_point_transactions', [
            'customer_id' => $customer->id,
            'type' => 'adjustment',
            'points' => -49,
            'balance_after' => 50,
        ]);
    }

    public function test_cannot_return_more_than_was_sold(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Milk Powder 400g',
            'sku' => 'SKU-MILK-400',
            'cost_price' => 500,
            'selling_price' => 650,
            'stock_qty' => 20,
            'reorder_level' => 5,
        ]);

        $this->actingAs($cashier)->post(route('cashier.billing.store'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'discount_percent' => 0,
            'points_to_redeem' => 0,
            'payment_method' => 'cash',
        ]);

        $sale = Sale::firstOrFail();
        $saleItem = SaleItem::where('sale_id', $sale->id)->firstOrFail();

        $response = $this->actingAs($cashier)->post(route('returns.store'), [
            'sale_id' => $sale->id,
            'items' => [
                ['sale_item_id' => $saleItem->id, 'quantity' => 5],
            ],
            'refund_method' => 'cash',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('sale_returns', 0);
        $this->assertEquals(18, $product->fresh()->stock_qty);
    }
}
