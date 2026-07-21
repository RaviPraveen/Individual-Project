<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function saleWithItem(Product $product, int $quantity): Sale
    {
        $lineTotal = $product->selling_price * $quantity;

        $sale = Sale::create([
            'invoice_no' => 'INV-ANALYTICS-'.uniqid(),
            'cashier_id' => User::factory()->create(['role' => 'cashier'])->id,
            'subtotal' => $lineTotal, 'discount' => 0, 'tax' => 0, 'total' => $lineTotal,
            'payment_method' => 'cash',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => $quantity,
            'unit_price' => $product->selling_price, 'line_total' => $lineTotal,
        ]);

        return $sale;
    }

    public function test_cashier_is_forbidden_from_the_analytics_page(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $this->actingAs($cashier)->get(route('admin.promotions.analytics'))->assertForbidden();
    }

    public function test_analytics_metrics_reflect_sales_made_during_the_promotion_window(): void
    {
        $admin = $this->admin();
        $product = Product::create([
            'name' => 'Rice 5kg', 'sku' => 'SKU-ANALYTICS', 'cost_price' => 1000,
            'selling_price' => 1500, 'stock_qty' => 50, 'reorder_level' => 5,
        ]);

        $promotion = Promotion::create([
            'title' => 'Analytics Promo', 'product_id' => $product->id,
            'current_price' => 1500, 'offer_price' => 1200, 'discount_percentage' => 20,
            'start_date' => now()->subHour(), 'end_date' => now()->addDay(),
            'display_duration' => 10, 'priority' => 'normal', 'status' => Promotion::STATUS_ACTIVE,
            'target_screen' => 'customer_display', 'display_count' => 40, 'created_by' => $admin->id,
        ]);

        // Within window — counted.
        $this->saleWithItem($product, 3);
        // A sale of the SAME product but made before the promotion started —
        // must not be counted.
        $outOfWindowSale = $this->saleWithItem($product, 5);
        $outOfWindowSale->forceFill(['created_at' => now()->subDays(3)])->save();

        $response = $this->actingAs($admin)->get(route('admin.promotions.analytics'));
        $response->assertOk();

        $rows = $response->viewData('rows');
        $row = $rows->firstWhere(fn ($r) => $r['promotion']->is($promotion));

        $this->assertSame(40, $row['metrics']['display_count']);
        $this->assertSame(40, $row['metrics']['views']);
        $this->assertSame(40, $row['metrics']['estimated_reach']);
        $this->assertSame(3, $row['metrics']['units_sold']);
        $this->assertEqualsWithDelta(4500.0, $row['metrics']['revenue'], 0.01);
        $this->assertEqualsWithDelta(7.5, $row['metrics']['conversion_rate'], 0.01); // 3/40 * 100
    }

    public function test_best_performers_are_sorted_by_revenue_descending(): void
    {
        $admin = $this->admin();
        $lowRevenue = Product::create(['name' => 'Low Rev', 'sku' => 'SKU-LOW', 'cost_price' => 100, 'selling_price' => 200, 'stock_qty' => 50, 'reorder_level' => 5]);
        $highRevenue = Product::create(['name' => 'High Rev', 'sku' => 'SKU-HIGH', 'cost_price' => 100, 'selling_price' => 2000, 'stock_qty' => 50, 'reorder_level' => 5]);

        $low = Promotion::create([
            'title' => 'Low Performer', 'product_id' => $lowRevenue->id,
            'current_price' => 200, 'offer_price' => 150, 'discount_percentage' => 25,
            'start_date' => now()->subHour(), 'end_date' => now()->addDay(),
            'display_duration' => 10, 'priority' => 'normal', 'status' => Promotion::STATUS_ACTIVE,
            'target_screen' => 'customer_display', 'created_by' => $admin->id,
        ]);
        $high = Promotion::create([
            'title' => 'High Performer', 'product_id' => $highRevenue->id,
            'current_price' => 2000, 'offer_price' => 1500, 'discount_percentage' => 25,
            'start_date' => now()->subHour(), 'end_date' => now()->addDay(),
            'display_duration' => 10, 'priority' => 'normal', 'status' => Promotion::STATUS_ACTIVE,
            'target_screen' => 'customer_display', 'created_by' => $admin->id,
        ]);

        $this->saleWithItem($lowRevenue, 1);
        $this->saleWithItem($highRevenue, 5);

        $response = $this->actingAs($admin)->get(route('admin.promotions.analytics'));
        $best = $response->viewData('best');

        $this->assertTrue($best->first()['promotion']->is($high));
    }

    public function test_recommendations_fall_back_to_computed_text_when_ai_is_unconfigured(): void
    {
        $admin = $this->admin();
        // High stock, essentially no sales in 30 days -> should trigger the
        // "high stock slow mover" signal, phrased without AI (test env has
        // no Hugging Face key configured).
        Product::create([
            'name' => 'Overstocked Item', 'sku' => 'SKU-OVERSTOCK', 'cost_price' => 100,
            'selling_price' => 200, 'stock_qty' => 500, 'reorder_level' => 10,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.promotions.index'));
        $response->assertOk();

        $recommendations = $response->viewData('recommendations');
        $this->assertNotEmpty($recommendations);
        $this->assertStringContainsString('Overstocked Item', $recommendations[0]['text']);
        $this->assertStringContainsString('discount', $recommendations[0]['text']);
    }

    public function test_recommendations_use_ai_phrasing_when_configured(): void
    {
        $admin = $this->admin();
        Product::create([
            'name' => 'Overstocked Item', 'sku' => 'SKU-OVERSTOCK-2', 'cost_price' => 100,
            'selling_price' => 200, 'stock_qty' => 500, 'reorder_level' => 10,
        ]);

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('generate')->once()->andReturn('Clear out the Overstocked Item pile with a flash sale!');
        });

        $response = $this->actingAs($admin)->get(route('admin.promotions.index'));
        $recommendations = $response->viewData('recommendations');

        $this->assertSame('Clear out the Overstocked Item pile with a flash sale!', $recommendations[0]['text']);
    }
}
