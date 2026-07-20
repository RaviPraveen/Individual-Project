<?php

namespace Tests\Feature;

use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_change_points_earn_rate_and_it_affects_new_sales(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        // Change the rate to 0.1%, from the 1% default.
        $this->actingAs($admin)->put(route('admin.billing-settings.update'), [
            'points_earn_percent' => 0.1,
            'points_redeem_value' => 1,
            'bag_fee' => 0,
        ])->assertRedirect();

        $this->assertDatabaseHas('billing_settings', [
            'points_earn_percent' => 0.1,
        ]);

        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Test Customer', 'phone' => '0770000000']);
        $product = Product::create([
            'name' => 'Sugar 1kg', 'sku' => 'SKU-SUGAR', 'cost_price' => 200, 'selling_price' => 500,
            'stock_qty' => 20, 'reorder_level' => 5,
        ]);

        $this->actingAs($cashier)->post(route('cashier.billing.store'), [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]], // Rs 1000 subtotal
            'discount_percent' => 0,
            'points_to_redeem' => 0,
            'payment_method' => 'cash',
        ])->assertRedirect();

        $sale = Sale::firstOrFail();

        // At the new 0.1% rate, a Rs 1000 bill earns floor(1000 * 0.001) = 1 point.
        $this->assertEquals(1, $sale->points_earned);
    }

    public function test_bag_fee_is_added_to_total_but_excluded_from_points_and_tax(): void
    {
        BillingSetting::current()->update(['bag_fee' => 20, 'points_earn_percent' => 1]);

        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Bag Buyer', 'phone' => '0771112222']);
        $product = Product::create([
            'name' => 'Rice 5kg', 'sku' => 'SKU-RICE', 'cost_price' => 900, 'selling_price' => 1000,
            'stock_qty' => 20, 'reorder_level' => 5,
        ]);

        $this->actingAs($cashier)->post(route('cashier.billing.store'), [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount_percent' => 0,
            'points_to_redeem' => 0,
            'payment_method' => 'cash',
            'wants_bag' => '1',
        ])->assertRedirect();

        $sale = Sale::firstOrFail();

        $this->assertEquals(1000.0, (float) $sale->subtotal);
        $this->assertEquals(20.0, (float) $sale->bag_fee);
        $this->assertEquals(1020.0, (float) $sale->total);

        // Points earned on the Rs 1000 merchandise (1% rate) = 10, not on
        // the Rs 1020 total that includes the bag fee.
        $this->assertEquals(10, $sale->points_earned);
    }

    public function test_bag_fee_is_not_added_when_not_requested(): void
    {
        BillingSetting::current()->update(['bag_fee' => 20]);

        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Milk Powder 400g', 'sku' => 'SKU-MILK', 'cost_price' => 500, 'selling_price' => 650,
            'stock_qty' => 20, 'reorder_level' => 5,
        ]);

        $this->actingAs($cashier)->post(route('cashier.billing.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount_percent' => 0,
            'points_to_redeem' => 0,
            'payment_method' => 'cash',
        ])->assertRedirect();

        $sale = Sale::firstOrFail();

        $this->assertEquals(0.0, (float) $sale->bag_fee);
        $this->assertEquals(650.0, (float) $sale->total);
    }
}
