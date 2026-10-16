<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ReceiptSetting;
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

    public function test_store_rejects_the_same_product_on_two_return_lines(): void
    {
        ['admin' => $admin, 'supplier' => $supplier, 'product' => $product] = $this->pendingReturn(stockQty: 5, returnQty: 1);

        $this->actingAs($admin)->post(route('admin.supplier-returns.store'), [
            'supplier_id' => $supplier->id,
            'return_date' => now()->format('Y-m-d'),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'reason' => 'damaged'],
                ['product_id' => $product->id, 'quantity' => 3, 'reason' => 'not_selling'],
            ],
        ])->assertSessionHasErrors(['items.0.product_id', 'items.1.product_id']);

        $this->assertDatabaseCount('supplier_returns', 1);
        $this->assertDatabaseCount('supplier_return_items', 1);
        $this->assertEquals(5, $product->fresh()->stock_qty);
    }

    public function test_existing_duplicate_return_lines_cannot_exceed_combined_stock(): void
    {
        ['admin' => $admin, 'product' => $product, 'supplierReturn' => $supplierReturn] = $this->pendingReturn(stockQty: 5, returnQty: 3);
        $supplierReturn->items()->create([
            'product_id' => $product->id, 'quantity' => 3, 'reason' => 'damaged',
        ]);

        $this->actingAs($admin)->post(route('admin.supplier-returns.complete', $supplierReturn))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertEquals('pending', $supplierReturn->fresh()->status);
        $this->assertEquals(5, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_existing_duplicate_return_lines_can_complete_with_enough_stock(): void
    {
        ['admin' => $admin, 'product' => $product, 'supplierReturn' => $supplierReturn] = $this->pendingReturn(stockQty: 10, returnQty: 3);
        $supplierReturn->items()->create([
            'product_id' => $product->id, 'quantity' => 2, 'reason' => 'damaged',
        ]);

        $this->actingAs($admin)->post(route('admin.supplier-returns.complete', $supplierReturn))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertEquals('completed', $supplierReturn->fresh()->status);
        $this->assertEquals(5, $product->fresh()->stock_qty);
        $this->assertEquals(5, $product->stockMovements()->sum('quantity'));
        $this->assertDatabaseCount('stock_movements', 2);
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

    public function test_credit_and_refund_can_be_saved_before_or_after_completion_without_changing_stock(): void
    {
        foreach (['pending', 'completed'] as $status) {
            foreach (['credit', 'refund'] as $resolution) {
                ['admin' => $admin, 'product' => $product, 'supplierReturn' => $return] = $this->pendingReturn();
                $this->actingAs($admin);
                if ($status === 'completed') {
                    $this->post(route('admin.supplier-returns.complete', $return))->assertRedirect();
                }
                $stock = $product->fresh()->stock_qty;
                $movements = $product->stockMovements()->count();

                $this->patch(route('admin.supplier-returns.settlement', $return), [
                    'resolution' => $resolution, 'credit_note_value' => '1250.50',
                ])->assertRedirect(route('admin.supplier-returns.show', $return))->assertSessionHas('success');

                $this->assertDatabaseHas('supplier_returns', [
                    'id' => $return->id, 'resolution' => $resolution, 'credit_note_value' => 1250.50, 'status' => $status,
                ]);
                $this->assertEquals($stock, $product->fresh()->stock_qty);
                $this->assertEquals($movements, $product->stockMovements()->count());
                $this->assertDatabaseHas('activity_logs', [
                    'user_id' => $admin->id, 'action' => 'supplier_return.settlement_updated', 'subject_id' => $return->id,
                ]);
            }
        }
    }

    public function test_replacement_or_unsettled_return_clears_the_previous_amount(): void
    {
        foreach (['replacement', 'none'] as $resolution) {
            ['admin' => $admin, 'supplierReturn' => $return] = $this->pendingReturn();
            $return->update(['resolution' => 'credit', 'credit_note_value' => 500]);

            $this->actingAs($admin)->patch(route('admin.supplier-returns.settlement', $return), [
                'resolution' => $resolution, 'credit_note_value' => 500,
            ])->assertRedirect()->assertSessionHas('success');

            $this->assertEquals($resolution, $return->fresh()->resolution);
            $this->assertEquals('0.00', $return->fresh()->credit_note_value);
        }
    }

    public function test_settlement_rejects_invalid_amounts_and_resolution(): void
    {
        ['admin' => $admin, 'supplierReturn' => $return] = $this->pendingReturn();
        $this->actingAs($admin);
        foreach ([null, 0, -1, '1.234', '10000000000', 'invalid'] as $amount) {
            $this->patch(route('admin.supplier-returns.settlement', $return), [
                'resolution' => 'credit', 'credit_note_value' => $amount,
            ])->assertSessionHasErrors('credit_note_value');
        }
        $this->patch(route('admin.supplier-returns.settlement', $return), [
            'resolution' => 'cash', 'credit_note_value' => 100,
        ])->assertSessionHasErrors('resolution');
        $this->assertEquals('none', $return->fresh()->resolution);
        $this->assertEquals('0.00', $return->fresh()->credit_note_value);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_settlement_accepts_the_database_amount_limit(): void
    {
        ['admin' => $admin, 'supplierReturn' => $return] = $this->pendingReturn();
        $this->actingAs($admin)->patch(route('admin.supplier-returns.settlement', $return), [
            'resolution' => 'refund', 'credit_note_value' => '9999999999.99',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertEquals('9999999999.99', $return->fresh()->credit_note_value);
    }

    public function test_cancelled_supplier_return_cannot_be_settled(): void
    {
        ['admin' => $admin, 'supplierReturn' => $return] = $this->pendingReturn();
        $return->update(['status' => 'cancelled']);
        $this->actingAs($admin)->patch(route('admin.supplier-returns.settlement', $return), [
            'resolution' => 'credit', 'credit_note_value' => 100,
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertEquals('none', $return->fresh()->resolution);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->get(route('admin.supplier-returns.show', $return))->assertOk()->assertDontSee('Save Settlement');
    }

    public function test_cashier_cannot_update_a_supplier_settlement(): void
    {
        ['supplierReturn' => $return] = $this->pendingReturn();
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $this->actingAs($cashier)->patch(route('admin.supplier-returns.settlement', $return), [
            'resolution' => 'credit', 'credit_note_value' => 100,
        ])->assertForbidden();
        $this->assertEquals('none', $return->fresh()->resolution);
    }

    public function test_saved_settlement_appears_in_return_details_and_pdf_template(): void
    {
        ['admin' => $admin, 'supplierReturn' => $return] = $this->pendingReturn();
        $this->actingAs($admin)->patch(route('admin.supplier-returns.settlement', $return), [
            'resolution' => 'credit', 'credit_note_value' => '1234.56',
        ])->assertRedirect();
        $this->get(route('admin.supplier-returns.show', $return))->assertOk()->assertSee('1,234.56')->assertSee('Save Settlement');
        $this->get(route('admin.supplier-returns.index'))->assertOk()->assertSee('1,234.56');
        $html = view('supplier-returns._body', [
            'settings' => ReceiptSetting::current(),
            'supplierReturn' => $return->fresh()->load('supplier', 'creator', 'items.product'),
        ])->render();
        $this->assertStringContainsString('1,234.56', $html);
        $this->assertStringContainsString('Credit / Refund Amount', $html);
    }

    public function test_saving_the_same_settlement_does_not_duplicate_the_log(): void
    {
        ['admin' => $admin, 'supplierReturn' => $return] = $this->pendingReturn();
        $this->actingAs($admin);
        foreach ([1, 2] as $attempt) {
            $this->patch(route('admin.supplier-returns.settlement', $return), [
                'resolution' => 'credit', 'credit_note_value' => '150.00',
            ])->assertRedirect();
        }
        $this->assertDatabaseCount('activity_logs', 1);
    }
}
