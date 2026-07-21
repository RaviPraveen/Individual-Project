<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierReturnTest extends TestCase
{
    use RefreshDatabase;

    private function pendingReturn(int $stockQty = 10, int $returnQty = 3): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Test Supplier']);
        $product = Product::create([
            'name' => 'Return Test Item', 'sku' => 'SKU-'.uniqid(), 'cost_price' => 100, 'selling_price' => 150,
            'stock_qty' => $stockQty, 'reorder_level' => 2,
        ]);

        $supplierReturn = SupplierReturn::create([
            'supplier_id' => $supplier->id,
            'created_by' => $admin->id,
            'return_date' => now(),
            'status' => 'pending',
        ]);

        $supplierReturn->items()->create([
            'product_id' => $product->id,
            'quantity' => $returnQty,
            'reason' => 'not_selling',
        ]);

        return compact('admin', 'supplier', 'product', 'supplierReturn');
    }

    public function test_cashier_is_forbidden_from_supplier_returns(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $this->actingAs($cashier)->get(route('admin.supplier-returns.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('admin.supplier-returns.create'))->assertForbidden();
        $this->actingAs($cashier)->post(route('admin.supplier-returns.store'), [])->assertForbidden();
    }

    public function test_store_rejects_quantity_exceeding_current_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Test Supplier']);
        $product = Product::create([
            'name' => 'Low Stock Item', 'sku' => 'SKU-LOWSTOCK', 'cost_price' => 100, 'selling_price' => 150,
            'stock_qty' => 5, 'reorder_level' => 2,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.supplier-returns.store'), [
            'supplier_id' => $supplier->id,
            'return_date' => now()->format('Y-m-d'),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10, 'reason' => 'not_selling'],
            ],
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('supplier_returns', 0);
    }

    public function test_store_creates_a_pending_draft_without_touching_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Test Supplier']);
        $product = Product::create([
            'name' => 'Draft Item', 'sku' => 'SKU-DRAFT', 'cost_price' => 100, 'selling_price' => 150,
            'stock_qty' => 10, 'reorder_level' => 2,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.supplier-returns.store'), [
            'supplier_id' => $supplier->id,
            'return_date' => now()->format('Y-m-d'),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'reason' => 'not_selling'],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('supplier_returns', ['supplier_id' => $supplier->id, 'status' => 'pending']);
        $this->assertEquals(10, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_a_pending_supplier_return_leaves_stock_untouched(): void
    {
        ['product' => $product] = $this->pendingReturn(stockQty: 10, returnQty: 3);

        $this->assertEquals(10, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_completing_a_supplier_return_decrements_stock_and_logs_a_stock_movement(): void
    {
        ['admin' => $admin, 'product' => $product, 'supplierReturn' => $supplierReturn] = $this->pendingReturn(stockQty: 10, returnQty: 3);

        $response = $this->actingAs($admin)->post(route('admin.supplier-returns.complete', $supplierReturn));

        $response->assertRedirect();
        $this->assertEquals(7, $product->fresh()->stock_qty);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => 3,
            'reason' => 'supplier_return',
        ]);
        $this->assertEquals('completed', $supplierReturn->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'supplier_return.completed']);
    }

    public function test_completing_a_supplier_return_rejects_when_stock_has_since_dropped_below_the_returned_quantity(): void
    {
        ['admin' => $admin, 'product' => $product, 'supplierReturn' => $supplierReturn] = $this->pendingReturn(stockQty: 10, returnQty: 8);

        // Simulate stock dropping (e.g. via sales) after the draft was created.
        $product->update(['stock_qty' => 2]);

        $response = $this->actingAs($admin)->post(route('admin.supplier-returns.complete', $supplierReturn));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals('pending', $supplierReturn->fresh()->status);
        $this->assertEquals(2, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_a_supplier_return_cannot_be_completed_twice(): void
    {
        ['admin' => $admin, 'product' => $product, 'supplierReturn' => $supplierReturn] = $this->pendingReturn(stockQty: 10, returnQty: 3);

        $this->actingAs($admin)->post(route('admin.supplier-returns.complete', $supplierReturn))->assertRedirect();
        $stockAfterFirstComplete = $product->fresh()->stock_qty;

        $response = $this->actingAs($admin)->post(route('admin.supplier-returns.complete', $supplierReturn));

        $response->assertSessionHas('error');
        $this->assertEquals($stockAfterFirstComplete, $product->fresh()->stock_qty);
    }

    public function test_cancelling_a_pending_return_has_no_stock_effect_and_logs_activity(): void
    {
        ['admin' => $admin, 'product' => $product, 'supplierReturn' => $supplierReturn] = $this->pendingReturn(stockQty: 10, returnQty: 3);

        $response = $this->actingAs($admin)->post(route('admin.supplier-returns.cancel', $supplierReturn));

        $response->assertRedirect();
        $this->assertEquals('cancelled', $supplierReturn->fresh()->status);
        $this->assertEquals(10, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('activity_logs', ['action' => 'supplier_return.cancelled']);
    }
}
