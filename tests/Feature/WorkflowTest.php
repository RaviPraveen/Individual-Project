<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PromotionPosterController;
use App\Models\AiLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    }

    private function product(array $values = []): Product
    {
        return Product::create($values + [
            'name' => 'Test Product', 'sku' => uniqid('TEST-'), 'cost_price' => 50,
            'selling_price' => 100, 'stock_qty' => 20, 'reorder_level' => 5, 'is_active' => true,
        ]);
    }

    private function sale(Product $product, string $date): Sale
    {
        $sale = Sale::create([
            'invoice_no' => uniqid('TEST-'), 'cashier_id' => auth()->id(),
            'subtotal' => 100, 'discount' => 0, 'tax' => 0, 'total' => 100,
            'payment_method' => 'cash', 'created_at' => $date,
        ]);
        $sale->items()->create([
            'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100,
            'cost_price' => 50, 'line_total' => 100,
        ]);

        return $sale;
    }

    private function promotionData(Product $product): array
    {
        return [
            'product_id' => $product->id, 'title' => 'Weekend offer', 'offer_price' => 80,
            'start_date' => now()->subDay()->toDateTimeString(), 'end_date' => now()->addDay()->toDateTimeString(),
            'display_duration' => 10, 'priority' => 'normal', 'target_screen' => 'customer_display',
        ];
    }

    public function test_customer_phone_validation_allows_unchanged_phone_but_rejects_duplicates(): void
    {
        $customer = Customer::create(['name' => 'Existing customer', 'phone' => '0770000000']);
        $other = Customer::create(['name' => 'Another customer', 'phone' => '0771111111']);

        $this->post(route('admin.customers.store'), ['name' => 'Duplicate', 'phone' => $customer->phone])
            ->assertSessionHasErrors('phone');
        $this->put(route('admin.customers.update', $other), ['name' => $other->name, 'phone' => $customer->phone])
            ->assertSessionHasErrors('phone');
        $this->put(route('admin.customers.update', $customer), ['name' => 'Updated name', 'phone' => $customer->phone])
            ->assertSessionHasNoErrors();
        $this->assertSame('Updated name', $customer->fresh()->name);
        $this->assertDatabaseCount('customers', 2);
    }

    public function test_quick_customer_validation_returns_json(): void
    {
        Customer::create(['name' => 'Existing customer', 'phone' => '0770000000']);
        $this->postJson(route('cashier.customers.quick-create'), ['name' => 'Duplicate', 'phone' => '0770000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_cashier_customer_enrollment_email_is_optional(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cashier', 'is_active' => true]));

        foreach ([[], ['email' => ''], ['email' => 'customer@example.com']] as $index => $email) {
            $phone = '077000000'.$index;
            $this->postJson(route('cashier.customers.quick-create'), $email + ['name' => 'Test Customer', 'phone' => $phone])
                ->assertOk();
            $this->assertDatabaseHas('customers', ['phone' => $phone, 'email' => ($email['email'] ?? '') ?: null]);
        }
    }

    public function test_cashier_customer_enrollment_rejects_invalid_email(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cashier', 'is_active' => true]));
        $this->postJson(route('cashier.customers.quick-create'), [
            'name' => 'Test Customer', 'phone' => '0770000000', 'email' => 'invalid-email',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_exact_barcode_and_sku_are_found_before_partial_matches(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->product(['name' => 'A product 123 '.$i]);
        }
        $barcode = $this->product(['name' => 'Z barcode match', 'barcode' => '123']);
        $sku = $this->product(['name' => 'Z SKU match', 'sku' => '123']);

        $this->getJson(route('products.search', ['q' => '123']))->assertOk()
            ->assertJsonCount(10)->assertJsonPath('0.id', $barcode->id)->assertJsonPath('1.id', $sku->id);
    }

    public function test_products_with_purchase_stock_or_supplier_return_history_are_deactivated(): void
    {
        $supplier = Supplier::create(['name' => 'Test Supplier']);
        $purchaseProduct = $this->product();
        $stockProduct = $this->product();
        $returnProduct = $this->product();
        $order = PurchaseOrder::create([
            'supplier_id' => $supplier->id, 'created_by' => auth()->id(),
            'order_date' => today(), 'status' => 'pending', 'total_amount' => 50,
        ]);
        $order->items()->create(['product_id' => $purchaseProduct->id, 'quantity' => 1, 'unit_cost' => 50]);
        StockMovement::create([
            'product_id' => $stockProduct->id, 'type' => 'adjustment', 'quantity' => 1,
            'reason' => 'Stock count', 'recorded_by' => auth()->id(),
        ]);
        $return = SupplierReturn::create([
            'supplier_id' => $supplier->id, 'created_by' => auth()->id(),
            'return_date' => today(), 'status' => 'pending',
        ]);
        $return->items()->create(['product_id' => $returnProduct->id, 'quantity' => 1, 'reason' => 'damaged']);

        foreach ([$purchaseProduct, $stockProduct, $returnProduct] as $product) {
            $this->delete(route('admin.products.destroy', $product))->assertRedirect()->assertSessionHas('success');
            $this->assertFalse($product->fresh()->is_active);
        }
        $this->assertDatabaseCount('purchase_order_items', 1);
        $this->assertDatabaseCount('supplier_return_items', 1);
    }

    public function test_supplier_with_return_history_is_kept(): void
    {
        $supplier = Supplier::create(['name' => 'Test Supplier']);
        SupplierReturn::create([
            'supplier_id' => $supplier->id, 'created_by' => auth()->id(),
            'return_date' => today(), 'status' => 'pending',
        ]);

        $this->delete(route('admin.suppliers.destroy', $supplier))->assertRedirect()->assertSessionHas('error');
        $this->assertModelExists($supplier);
    }

    public function test_users_with_ai_logs_or_supplier_returns_are_deactivated(): void
    {
        $chatUser = User::factory()->create(['role' => 'cashier']);
        $returnUser = User::factory()->create(['role' => 'admin']);
        AiLog::create(['user_id' => $chatUser->id, 'query' => 'Stock?', 'response' => 'Test response']);
        $supplier = Supplier::create(['name' => 'Test Supplier']);
        SupplierReturn::create([
            'supplier_id' => $supplier->id, 'created_by' => $returnUser->id,
            'return_date' => today(), 'status' => 'pending',
        ]);

        foreach ([$chatUser, $returnUser] as $user) {
            $this->delete(route('admin.users.destroy', $user))->assertRedirect()->assertSessionHas('success');
            $this->assertFalse($user->fresh()->is_active);
        }
        $this->assertDatabaseCount('ai_logs', 1);
    }

    public function test_unused_records_can_still_be_deleted(): void
    {
        $product = $this->product();
        $supplier = Supplier::create(['name' => 'Unused Supplier']);
        $user = User::factory()->create(['role' => 'cashier']);
        $this->delete(route('admin.products.destroy', $product))->assertRedirect();
        $this->delete(route('admin.suppliers.destroy', $supplier))->assertRedirect();
        $this->delete(route('admin.users.destroy', $user))->assertRedirect();
        $this->assertModelMissing($product);
        $this->assertModelMissing($supplier);
        $this->assertModelMissing($user);
    }

    public function test_admin_display_sync_uses_its_own_cart(): void
    {
        $admin = auth()->user();
        $this->postJson(route('cashier.display.sync'), [
            'items' => [['name' => 'Rice', 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100]],
            'subtotal' => 100, 'discount' => 0, 'tax' => 0, 'total' => 100,
        ])->assertOk();
        $this->getJson(route('cashier.display.data'))->assertJsonPath('items.0.name', 'Rice');
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $this->actingAs($cashier)->getJson(route('cashier.display.data'))->assertJsonPath('status', 'idle');
        $this->actingAs($admin)->getJson(route('cashier.display.data'))->assertJsonPath('total', 100);
    }

    public function test_refund_lookup_supplies_discount_tax_and_redemption_for_preview(): void
    {
        Setting::set('tax_rate', '10');
        $sale = $this->sale($this->product(), now()->toDateTimeString());
        $sale->update(['discount' => 10, 'tax' => 9, 'redemption_value' => 20, 'total' => 79]);

        $this->getJson(route('returns.lookup', ['invoice_no' => $sale->invoice_no]))->assertOk()
            ->assertJsonPath('subtotal', 100)->assertJsonPath('discount', 10)
            ->assertJsonPath('refund_tax_percent', 10)->assertJsonPath('redemption_value', 20);
        $this->post(route('returns.store'), [
            'sale_id' => $sale->id, 'refund_method' => 'cash',
            'items' => [['sale_item_id' => $sale->items()->first()->id, 'quantity' => 1]],
        ])->assertSessionMissing('error');
        $this->assertEquals(79, SaleReturn::firstOrFail()->total_refunded);
    }

    public function test_promotion_date_edits_refresh_status_and_preserve_manual_pause(): void
    {
        $data = $this->promotionData($this->product());
        $promotion = Promotion::create($data + ['current_price' => 100, 'discount_percentage' => 20, 'status' => 'expired']);

        $this->put(route('admin.promotions.update', $promotion), $data)->assertSessionHasNoErrors();
        $this->assertSame('active', $promotion->fresh()->status);
        $this->assertTrue(Promotion::visibleOnDisplay()->whereKey($promotion->id)->exists());
        $future = array_replace($data, ['start_date' => now()->addDay(), 'end_date' => now()->addDays(2)]);
        $this->put(route('admin.promotions.update', $promotion), $future)->assertSessionHasNoErrors();
        $this->assertSame('scheduled', $promotion->fresh()->status);
        $promotion->update(['status' => 'paused']);
        $this->put(route('admin.promotions.update', $promotion), $data)->assertSessionHasNoErrors();
        $this->assertSame('paused', $promotion->fresh()->status);
    }

    public function test_month_end_chart_has_twelve_months_and_comparison_excludes_current_month(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 31)->setTime(12, 0));
        $this->sale($this->product(), '2026-03-01 12:00:00');
        $response = $this->get(route('admin.revenue.index'))->assertOk();
        $labels = $response->viewData('monthlyTrend')['labels'];

        $this->assertCount(12, array_unique($labels));
        $this->assertSame('Feb 2026', $labels[10]);
        $this->assertSame('Mar 2026', $labels[11]);
        $this->assertEquals(100, $response->viewData('month')['change_percent']);
    }

    public function test_filtered_dead_stock_recommendations_use_the_matching_products(): void
    {
        $a = Category::create(['name' => 'Category A']);
        $b = Category::create(['name' => 'Category B']);
        $this->product(['name' => 'Product A', 'category_id' => $a->id]);
        $this->product(['name' => 'Product B', 'category_id' => $b->id]);
        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('generate')->once()->withArgs(fn ($prompt) => str_contains($prompt, 'Product A'))->andReturn('Advice for A');
            $mock->shouldReceive('generate')->once()->withArgs(fn ($prompt) => str_contains($prompt, 'Product B'))->andReturn('Advice for B');
        });

        $this->get(route('admin.reports.dead-stock', ['category_id' => $a->id]))->assertViewHas('narrative', 'Advice for A');
        $this->get(route('admin.reports.dead-stock', ['category_id' => $b->id]))->assertViewHas('narrative', 'Advice for B');
        $this->get(route('admin.reports.dead-stock', ['category_id' => $a->id]))->assertViewHas('narrative', 'Advice for A');
    }

    public function test_changed_offer_clears_old_live_and_pending_posters(): void
    {
        Storage::fake('public');
        $data = $this->promotionData($this->product());
        $promotion = Promotion::create($data + [
            'current_price' => 100, 'discount_percentage' => 20, 'status' => 'active',
            'poster_path' => 'promotions/old.jpg', 'pending_poster_path' => 'promotions/pending/old.jpg', 'poster_source' => 'ai',
        ]);
        Storage::disk('public')->put($promotion->poster_path, 'old');
        Storage::disk('public')->put($promotion->pending_poster_path, 'pending');

        $this->put(route('admin.promotions.update', $promotion), array_replace($data, ['offer_price' => 70]))
            ->assertRedirect(route('admin.promotions.edit', $promotion))->assertSessionHas('success');
        $this->assertNull($promotion->fresh()->poster_path);
        $this->assertNull($promotion->fresh()->pending_poster_path);
        Storage::disk('public')->assertMissing('promotions/old.jpg');
        Storage::disk('public')->assertMissing('promotions/pending/old.jpg');
        $this->postJson(route('admin.promotions.poster.approve', $promotion))->assertUnprocessable();
    }

    public function test_unrelated_promotion_edits_keep_the_poster(): void
    {
        Storage::fake('public');
        $data = $this->promotionData($this->product());
        $promotion = Promotion::create($data + [
            'current_price' => 100, 'discount_percentage' => 20, 'status' => 'active', 'poster_path' => 'promotions/live.jpg',
        ]);
        Storage::disk('public')->put($promotion->poster_path, 'live');
        $this->put(route('admin.promotions.update', $promotion), array_replace($data, ['priority' => 'high']))->assertSessionHasNoErrors();
        $this->assertSame('promotions/live.jpg', $promotion->fresh()->poster_path);
        Storage::disk('public')->assertExists('promotions/live.jpg');
    }

    public function test_draft_poster_cannot_be_used_after_its_offer_changes(): void
    {
        Storage::fake('public');
        $data = $this->promotionData($this->product());
        $this->mock(AiService::class, fn ($mock) => $mock->shouldReceive('generateImage')->once()->andReturn(null));
        $this->postJson(route('admin.promotions.poster.generate-draft'), $data)->assertOk();
        $draft = session(PromotionPosterController::DRAFT_SESSION_KEY);

        $this->post(route('admin.promotions.store'), array_replace($data, ['offer_price' => 70, 'use_generated_poster' => 1]))
            ->assertSessionHasErrors('poster_image');
        $this->assertDatabaseCount('promotions', 0);
        Storage::disk('public')->assertExists($draft['path']);
    }
}
