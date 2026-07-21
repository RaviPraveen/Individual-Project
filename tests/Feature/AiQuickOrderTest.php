<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiQuickOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_unconfigured_when_ai_service_has_no_api_key(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $response = $this->actingAs($cashier)->postJson(route('cashier.billing.parse-order'), [
            'text' => '2kg rice',
        ]);

        $response->assertOk()->assertJson(['configured' => false, 'items' => []]);
    }

    public function test_matches_ai_response_to_real_products_and_caps_quantity_at_stock(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $rice = Product::create([
            'name' => 'Rice 5kg', 'sku' => 'SKU-RICE', 'cost_price' => 900, 'selling_price' => 1200,
            'stock_qty' => 3, 'reorder_level' => 5,
        ]);
        $milk = Product::create([
            'name' => 'Milk Powder 400g', 'sku' => 'SKU-MILK', 'cost_price' => 500, 'selling_price' => 650,
            'stock_qty' => 10, 'reorder_level' => 5,
        ]);

        $this->mock(AiService::class, function ($mock) use ($rice, $milk) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('generate')->andReturn(json_encode([
                ['product_id' => $rice->id, 'quantity' => 10], // exceeds stock of 3
                ['product_id' => $milk->id, 'quantity' => 2],
                ['product_id' => 99999, 'quantity' => 1], // not a real product — must be dropped
            ]));
        });

        $response = $this->actingAs($cashier)->postJson(route('cashier.billing.parse-order'), [
            'text' => '2kg rice and some milk powder',
        ]);

        $response->assertOk();
        $items = $response->json('items');

        $this->assertCount(2, $items);
        $this->assertEquals($rice->id, $items[0]['product_id']);
        $this->assertEquals(3, $items[0]['quantity']); // capped at stock_qty
        $this->assertEquals($milk->id, $items[1]['product_id']);
        $this->assertEquals(2, $items[1]['quantity']);
    }

    public function test_returns_error_when_ai_response_is_not_valid_json(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('generate')->andReturn('Sorry, I cannot help with that.');
        });

        $response = $this->actingAs($cashier)->postJson(route('cashier.billing.parse-order'), [
            'text' => 'gibberish order',
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('error'));
        $this->assertEmpty($response->json('items'));
    }
}
