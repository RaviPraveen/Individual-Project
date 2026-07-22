<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueReportTest extends TestCase
{
    use RefreshDatabase;

    private function saleWithItem(Product $product, int $quantity): Sale
    {
        $lineTotal = $product->selling_price * $quantity;

        $sale = Sale::create([
            'invoice_no' => 'INV-TEST-'.uniqid(),
            'cashier_id' => User::factory()->create(['role' => 'cashier'])->id,
            'subtotal' => $lineTotal,
            'discount' => 0,
            'tax' => 0,
            'total' => $lineTotal,
            'payment_method' => 'cash',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->selling_price,
            'cost_price' => $product->cost_price,
            'line_total' => $lineTotal,
        ]);

        return $sale;
    }

    public function test_cashier_is_forbidden_from_every_revenue_route(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $this->actingAs($cashier)->get(route('admin.revenue.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('admin.revenue.by-period'))->assertForbidden();
        $this->actingAs($cashier)->get(route('admin.revenue.by-product'))->assertForbidden();
        $this->actingAs($cashier)->get(route('admin.revenue.by-category'))->assertForbidden();
    }

    public function test_revenue_overview_figures_are_mathematically_consistent(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $category = Category::create(['name' => 'Groceries']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Rice 5kg', 'sku' => 'SKU-RICE-REV',
            'cost_price' => 1000, 'selling_price' => 1500, 'stock_qty' => 50, 'reorder_level' => 5,
        ]);

        $this->saleWithItem($product, 3);

        $response = $this->actingAs($admin)->get(route('admin.revenue.index'));
        $response->assertOk();

        $today = $response->viewData('today');

        $this->assertEqualsWithDelta($today['revenue'] - $today['cost'], $today['profit'], 0.01);
        $this->assertEqualsWithDelta(
            round($today['profit'] / $today['revenue'] * 100, 1),
            $today['margin_percent'],
            0.05,
        );
        $this->assertEqualsWithDelta(4500.0, $today['revenue'], 0.01);
        $this->assertEqualsWithDelta(3000.0, $today['cost'], 0.01);
        $this->assertEqualsWithDelta(1500.0, $today['profit'], 0.01);
    }

    public function test_revenue_by_product_flags_low_margin_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $thinMargin = Product::create([
            'name' => 'Thin Margin Item', 'sku' => 'SKU-THIN', 'cost_price' => 95,
            'selling_price' => 100, 'stock_qty' => 20, 'reorder_level' => 5,
        ]);
        $healthyMargin = Product::create([
            'name' => 'Healthy Margin Item', 'sku' => 'SKU-HEALTHY', 'cost_price' => 50,
            'selling_price' => 150, 'stock_qty' => 20, 'reorder_level' => 5,
        ]);

        $this->saleWithItem($thinMargin, 1);
        $this->saleWithItem($healthyMargin, 1);

        $response = $this->actingAs($admin)->get(route('admin.revenue.by-product'));
        $response->assertOk();

        $rows = $response->viewData('rows')->keyBy('name');

        $this->assertLessThan(10, $rows['Thin Margin Item']->margin_percent);
        $this->assertGreaterThan(10, $rows['Healthy Margin Item']->margin_percent);
    }

    public function test_revenue_by_product_shows_the_products_actual_buying_and_selling_price(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Rice 5kg', 'sku' => 'SKU-RICE-PRICES', 'cost_price' => 1000,
            'selling_price' => 1200, 'stock_qty' => 20, 'reorder_level' => 5,
        ]);
        $this->saleWithItem($product, 1);

        $response = $this->actingAs($admin)->get(route('admin.revenue.by-product'));
        $row = $response->viewData('rows')->firstWhere('name', 'Rice 5kg');

        $this->assertEqualsWithDelta(1000.0, (float) $row->buying_price, 0.01);
        $this->assertEqualsWithDelta(1200.0, (float) $row->selling_price, 0.01);
    }

    public function test_revenue_by_period_csv_export_streams_correctly(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Milk 1L', 'sku' => 'SKU-MILK-REV', 'cost_price' => 200,
            'selling_price' => 300, 'stock_qty' => 30, 'reorder_level' => 5,
        ]);

        $this->saleWithItem($product, 2);

        $response = $this->actingAs($admin)->get(route('admin.revenue.by-period', ['export' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
