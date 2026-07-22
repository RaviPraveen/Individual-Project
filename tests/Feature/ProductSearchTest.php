<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_products_never_appear_even_when_sku_or_barcode_matches(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        Product::create([
            'name' => 'Discontinued Rice', 'sku' => 'SKU-DISCONTINUED', 'barcode' => '1234567890123',
            'cost_price' => 100, 'selling_price' => 150, 'stock_qty' => 0, 'reorder_level' => 5,
            'is_active' => false,
        ]);

        $bySku = $this->actingAs($cashier)->getJson(route('products.search', ['q' => 'SKU-DISCONTINUED']));
        $byBarcode = $this->actingAs($cashier)->getJson(route('products.search', ['q' => '1234567890123']));
        $byName = $this->actingAs($cashier)->getJson(route('products.search', ['q' => 'Discontinued']));

        $bySku->assertOk()->assertJsonCount(0);
        $byBarcode->assertOk()->assertJsonCount(0);
        $byName->assertOk()->assertJsonCount(0);
    }

    public function test_search_matches_by_name_sku_or_barcode(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        Product::create([
            'name' => 'Basmati Rice 5kg', 'sku' => 'SKU-BASMATI', 'barcode' => '9998887776665',
            'cost_price' => 1000, 'selling_price' => 1500, 'stock_qty' => 20, 'reorder_level' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($cashier)->getJson(route('products.search', ['q' => 'Basmati']))->assertOk()->assertJsonCount(1);
        $this->actingAs($cashier)->getJson(route('products.search', ['q' => 'SKU-BASMATI']))->assertOk()->assertJsonCount(1);
        $this->actingAs($cashier)->getJson(route('products.search', ['q' => '9998887776665']))->assertOk()->assertJsonCount(1);
    }
}
