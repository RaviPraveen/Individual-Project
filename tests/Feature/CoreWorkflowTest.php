<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Test Product',
            'sku' => 'SKU-'.uniqid(),
            'cost_price' => 100,
            'selling_price' => 150,
            'stock_qty' => 10,
            'reorder_level' => 5,
        ], $overrides));
    }

    private function purchaseOrderWithItem(int $quantity = 5): PurchaseOrder
    {
        $supplier = Supplier::create(['name' => 'Test Supplier']);
        $product = $this->product(['stock_qty' => 10]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
            'order_date' => now(),
            'status' => 'pending',
            'total_amount' => $quantity * 80,
        ]);

        $po->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_cost' => 80,
        ]);

        return $po->fresh('items');
    }

    // ─── Billing: success path ──────────────────────────────────────────────

    public function test_billing_completes_a_sale_and_decrements_stock(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = $this->product(['stock_qty' => 10]);

        $response = $this->actingAs($cashier)->post(route('cashier.billing.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'discount_percent' => 0,
            'points_to_redeem' => 0,
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('sales', 1);

        $sale = Sale::firstOrFail();
        $this->assertEquals(450.0, (float) $sale->total);
        $this->assertEquals(7, $product->fresh()->stock_qty);
    }

    public function test_billing_records_a_stock_movement_for_each_sale_item(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = $this->product(['stock_qty' => 10]);

        $this->actingAs($cashier)->post(route('cashier.billing.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'discount_percent' => 0,
            'points_to_redeem' => 0,
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => 2,
            'reason' => 'sale',
        ]);
    }

    // ─── Billing: rollback on insufficient stock ────────────────────────────

    public function test_billing_rolls_back_the_whole_sale_when_stock_is_insufficient(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = $this->product(['stock_qty' => 2]);

        $response = $this->actingAs($cashier)->post(route('cashier.billing.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'discount_percent' => 0,
            'points_to_redeem' => 0,
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect(route('cashier.billing.index'));
        $response->assertSessionHas('error');

        // Nothing should have been persisted — no sale, no stock change.
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertEquals(2, $product->fresh()->stock_qty);
    }

    public function test_billing_rollback_also_leaves_other_line_items_stock_untouched(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $inStock = $this->product(['stock_qty' => 10]);
        $outOfStock = $this->product(['stock_qty' => 1]);

        $this->actingAs($cashier)->post(route('cashier.billing.store'), [
            'items' => [
                ['product_id' => $inStock->id, 'quantity' => 3],
                ['product_id' => $outOfStock->id, 'quantity' => 5],
            ],
            'discount_percent' => 0,
            'points_to_redeem' => 0,
            'payment_method' => 'cash',
        ])->assertSessionHas('error');

        // The in-stock item must NOT have been decremented just because a
        // later item in the same cart failed — the whole transaction rolls back.
        $this->assertEquals(10, $inStock->fresh()->stock_qty);
        $this->assertEquals(1, $outOfStock->fresh()->stock_qty);
        $this->assertDatabaseCount('sales', 0);
    }

    // ─── Purchase order: receipt restocking + locking ───────────────────────

    public function test_marking_a_purchase_order_received_restocks_products(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $po = $this->purchaseOrderWithItem(5);
        $product = $po->items->first()->product;
        $startingStock = $product->stock_qty;

        $this->actingAs($admin)
            ->post(route('admin.purchase-orders.mark-received', $po))
            ->assertRedirect();

        $this->assertEquals($startingStock + 5, $product->fresh()->stock_qty);
        $this->assertEquals('received', $po->fresh()->status);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 5,
            'reason' => 'purchase',
        ]);
    }

    public function test_a_purchase_order_cannot_be_marked_received_twice(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $po = $this->purchaseOrderWithItem(5);
        $product = $po->items->first()->product;

        $this->actingAs($admin)->post(route('admin.purchase-orders.mark-received', $po))->assertRedirect();
        $stockAfterFirstReceive = $product->fresh()->stock_qty;

        // Simulates what the lockForUpdate + inside-transaction status
        // recheck exists to prevent: a second "mark received" against an
        // already-received PO must be rejected, not double-restock it.
        $response = $this->actingAs($admin)->post(route('admin.purchase-orders.mark-received', $po));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals($stockAfterFirstReceive, $product->fresh()->stock_qty);
    }

    public function test_a_cancelled_purchase_order_cannot_then_be_marked_received(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $po = $this->purchaseOrderWithItem(5);
        $product = $po->items->first()->product;
        $startingStock = $product->stock_qty;

        $this->actingAs($admin)->post(route('admin.purchase-orders.cancel', $po))->assertRedirect();
        $this->assertEquals('cancelled', $po->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.purchase-orders.mark-received', $po))
            ->assertSessionHas('error');

        $this->assertEquals($startingStock, $product->fresh()->stock_qty);
    }

    // ─── Role middleware: 403s for both roles ───────────────────────────────

    public function test_cashier_is_forbidden_from_admin_only_routes(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $this->actingAs($cashier)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($cashier)->get(route('admin.products.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_admin_is_forbidden_from_cashier_only_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('cashier.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('cashier.billing.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('cashier.display.show'))->assertForbidden();
    }

    public function test_deactivated_user_is_forbidden_even_with_the_correct_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => false]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_guests_are_redirected_to_login_for_protected_routes(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('cashier.billing.index'))->assertRedirect(route('login'));
    }

    // ─── New modules: activity log, settings, notifications, near-expiry ───

    public function test_activity_log_records_a_sale_and_is_visible_to_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = $this->product(['stock_qty' => 10]);

        $this->actingAs($cashier)->post(route('cashier.billing.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount_percent' => 0,
            'points_to_redeem' => 0,
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $cashier->id,
            'action' => 'sale.created',
        ]);

        $this->actingAs($admin)->get(route('admin.activity-log.index'))->assertOk();
    }

    public function test_admin_settings_update_is_read_back_by_billing(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'tax_rate' => 10,
            'currency_symbol' => 'Rs',
            'low_stock_threshold_default' => 5,
        ])->assertRedirect();

        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = $this->product(['stock_qty' => 10, 'selling_price' => 100]);

        $this->actingAs($cashier)->post(route('cashier.billing.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount_percent' => 0,
            'points_to_redeem' => 0,
            'payment_method' => 'cash',
        ])->assertRedirect();

        $sale = Sale::firstOrFail();

        // Rs 100 subtotal at the newly configured 10% tax rate.
        $this->assertEquals(10.0, (float) $sale->tax);
        $this->assertEquals(110.0, (float) $sale->total);
    }

    public function test_near_expiry_report_only_lists_products_within_the_window(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Category::create(['name' => 'Dairy']);

        $expiringSoon = $this->product(['name' => 'Milk', 'expiry_date' => now()->addDays(3)]);
        $this->product(['name' => 'Canned Beans', 'expiry_date' => now()->addDays(60)]);
        $this->product(['name' => 'Rice', 'expiry_date' => null]);

        $response = $this->actingAs($admin)->get(route('admin.reports.near-expiry', ['days' => 7]));

        $response->assertOk();
        $response->assertSee('Milk');
        $response->assertDontSee('Canned Beans');
        $response->assertDontSee('Rice');
    }

    public function test_low_stock_notification_is_generated_and_can_be_marked_read(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->product(['name' => 'Low Stock Item', 'stock_qty' => 1, 'reorder_level' => 5]);

        // Visiting any admin page runs the notification generator (via the
        // shared navigation view composer).
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $this->assertDatabaseHas('notifications', ['type' => 'low_stock', 'is_read' => false]);

        $notification = \App\Models\Notification::firstWhere('type', 'low_stock');

        $this->actingAs($admin)
            ->post(route('admin.notifications.read', $notification))
            ->assertRedirect();

        $this->assertTrue($notification->fresh()->is_read);
    }
}
