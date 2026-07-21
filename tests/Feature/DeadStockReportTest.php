<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeadStockReportTest extends TestCase
{
    use RefreshDatabase;

    private function saleWithItem(Product $product, int $quantity, ?\Illuminate\Support\Carbon $createdAt = null): Sale
    {
        $sale = Sale::create([
            'invoice_no' => 'INV-TEST-'.uniqid(),
            'cashier_id' => User::factory()->create(['role' => 'cashier'])->id,
            'subtotal' => $product->selling_price * $quantity,
            'discount' => 0,
            'tax' => 0,
            'total' => $product->selling_price * $quantity,
            'payment_method' => 'cash',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->selling_price,
            'line_total' => $product->selling_price * $quantity,
        ]);

        if ($createdAt) {
            $sale->created_at = $createdAt;
            $sale->save();
        }

        return $sale;
    }

    public function test_cashier_is_forbidden_from_dead_stock_report(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $this->actingAs($cashier)->get(route('admin.reports.dead-stock'))->assertForbidden();
    }

    public function test_dead_stock_report_includes_products_with_zero_sales_in_period(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Untouched Item', 'sku' => 'SKU-DEAD-1', 'cost_price' => 100, 'selling_price' => 150,
            'stock_qty' => 20, 'reorder_level' => 5,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.dead-stock'));

        $response->assertOk();
        $response->assertSee('Untouched Item');
    }

    public function test_dead_stock_report_excludes_products_above_the_max_qty_sold_threshold(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $fastMover = Product::create([
            'name' => 'Fast Mover', 'sku' => 'SKU-FAST-1', 'cost_price' => 100, 'selling_price' => 150,
            'stock_qty' => 20, 'reorder_level' => 5,
        ]);
        $this->saleWithItem($fastMover, 50);

        $response = $this->actingAs($admin)->get(route('admin.reports.dead-stock', ['max_qty_sold' => 3]));

        $response->assertOk();
        $response->assertDontSee('Fast Mover');
    }

    public function test_dead_stock_report_computes_velocity_and_stock_value_correctly(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Velocity Test Item', 'sku' => 'SKU-VEL-1', 'cost_price' => 50, 'selling_price' => 100,
            'stock_qty' => 10, 'reorder_level' => 2,
        ]);
        $this->saleWithItem($product, 2);

        $response = $this->actingAs($admin)->get(route('admin.reports.dead-stock', ['days' => 14]));

        $response->assertOk();
        // stock_value = 10 * 50 = 500.00; velocity = 2 / (14/7) = 1.0
        $response->assertSee('500.00');
        $response->assertSee('Velocity Test Item');
    }

    public function test_dead_stock_csv_export_returns_expected_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Category::create(['name' => 'Snacks']);
        Product::create([
            'name' => 'CSV Export Item', 'sku' => 'SKU-CSV-1', 'cost_price' => 100, 'selling_price' => 150,
            'stock_qty' => 5, 'reorder_level' => 2,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.dead-stock', ['export' => 'csv']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('CSV Export Item', $content);
        $this->assertStringContainsString('SKU-CSV-1', $content);
    }
}
