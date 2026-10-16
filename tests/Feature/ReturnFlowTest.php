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

        // Include discount, tax and loyalty points to check the partial refund.
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

        // Returning half the sale should refund half the discount, tax and total.
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

    // Keep the lookup response keys in sync with the return form's JavaScript.
    public function test_return_lookup_json_matches_the_shape_the_frontend_depends_on(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Sugar 1kg', 'sku' => 'SKU-SUGAR-LOOKUP', 'cost_price' => 200, 'selling_price' => 500,
            'stock_qty' => 20, 'reorder_level' => 5,
        ]);

        $this->actingAs($cashier)->post(route('cashier.billing.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'discount_percent' => 0,
            'points_to_redeem' => 0,
            'payment_method' => 'cash',
        ])->assertRedirect();

        $sale = Sale::firstOrFail();

        $response = $this->actingAs($cashier)
            ->getJson(route('returns.lookup', ['invoice_no' => $sale->invoice_no]));

        $response->assertOk();
        $response->assertJsonStructure([
            'sale_id', 'invoice_no', 'date', 'customer_name', 'total',
            'items' => [['sale_item_id', 'product_name', 'quantity', 'unit_price', 'already_returned', 'max_returnable']],
        ]);
        $response->assertJson(['sale_id' => $sale->id]);
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

    public function test_duplicate_sale_item_rows_do_not_refund_or_restock_twice(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Return Test Milk', 'sku' => 'SKU-DUPLICATE-RETURN',
            'cost_price' => 200, 'selling_price' => 300, 'stock_qty' => 20, 'reorder_level' => 5,
        ]);
        $this->actingAs($cashier)->post(route('cashier.billing.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
        ])->assertRedirect();

        $sale = Sale::firstOrFail();
        $saleItem = $sale->items()->firstOrFail();

        $this->post(route('returns.store'), [
            'sale_id' => $sale->id,
            'items' => [
                ['sale_item_id' => $saleItem->id, 'quantity' => 2],
                ['sale_item_id' => $saleItem->id, 'quantity' => 2],
            ],
            'refund_method' => 'cash',
        ])->assertSessionHasErrors(['items.0.sale_item_id', 'items.1.sale_item_id']);

        $this->assertDatabaseCount('sale_returns', 0);
        $this->assertDatabaseCount('sale_return_items', 0);
        $this->assertEquals(18, $product->fresh()->stock_qty);
        $this->assertDatabaseMissing('stock_movements', ['reason' => 'return']);
    }
}
