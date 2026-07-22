<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPosAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_the_shared_pos_billing_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('cashier.billing.index'))->assertOk();
    }

    public function test_admin_can_complete_a_sale_through_the_shared_pos(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Rice 5kg', 'sku' => 'SKU-ADMIN-POS', 'cost_price' => 1000,
            'selling_price' => 1500, 'stock_qty' => 10, 'reorder_level' => 5,
        ]);

        $response = $this->actingAs($admin)->post(route('cashier.billing.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount_percent' => 0,
            'points_to_redeem' => 0,
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sales', ['cashier_id' => $admin->id]);
    }

    public function test_admin_still_cannot_reach_the_cashier_only_dashboard_or_customer_display(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('cashier.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('cashier.display.show'))->assertForbidden();
    }

    public function test_cashier_still_cannot_reach_admin_only_areas(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $this->actingAs($cashier)->get(route('admin.dashboard'))->assertForbidden();
    }
}
