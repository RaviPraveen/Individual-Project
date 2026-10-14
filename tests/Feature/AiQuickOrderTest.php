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

        $response->assertOk()->assertJson(['configured' => false, 'items' => [], 'unmatched' => []]);
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
        $response->assertJsonPath('unmatched', []);
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

    public function test_mixed_order_keeps_unmatched_items_without_creating_a_sale(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $rice = $this->product();
        $order = json_encode([
            'items' => [['product_id' => $rice->id, 'quantity' => 2]],
            'unmatched' => ['1kg special red rice'],
        ]);

        $this->mock(AiService::class, function ($mock) use ($order) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('generate')->andReturn("```json\n{$order}\n```");
        });

        $response = $this->actingAs($cashier)->postJson(route('cashier.billing.parse-order'), [
            'text' => '2 rice and 1kg special red rice',
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.product_id', $rice->id)
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('unmatched', ['1kg special red rice']);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertEquals(10, $rice->fresh()->stock_qty);
    }

    public function test_order_can_contain_only_unmatched_items(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('generate')->andReturn(json_encode([
                'items' => [],
                'unmatched' => ['2kg samba rice', '1 packet special tea'],
            ]));
        });

        $this->actingAs($cashier)->postJson(route('cashier.billing.parse-order'), [
            'text' => '2kg samba rice and 1 packet special tea',
        ])->assertOk()->assertExactJson([
            'configured' => true,
            'items' => [],
            'unmatched' => ['2kg samba rice', '1 packet special tea'],
        ]);
    }

    public function test_order_ignores_bad_rows_and_unavailable_products(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $rice = $this->product();
        $inactive = $this->product(['sku' => 'INACTIVE', 'is_active' => false]);
        $empty = $this->product(['sku' => 'EMPTY', 'stock_qty' => 0]);

        $this->mock(AiService::class, function ($mock) use ($rice, $inactive, $empty) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('generate')->andReturn(json_encode([
                'items' => [
                    ['product_id' => $rice->id],
                    ['product_id' => $rice->id, 'quantity' => 'two'],
                    ['product_id' => $rice->id, 'quantity' => -1],
                    ['product_id' => $rice->id, 'quantity' => 1.5],
                    ['product_id' => true, 'quantity' => 1],
                    ['product_id' => $rice->id, 'quantity' => true],
                    ['product_id' => 99999, 'quantity' => 1],
                    ['product_id' => $inactive->id, 'quantity' => 1],
                    ['product_id' => $empty->id, 'quantity' => 1],
                    'not a product',
                ],
                'unmatched' => [],
            ]));
        });

        $this->actingAs($cashier)->postJson(route('cashier.billing.parse-order'), [
            'text' => 'rice',
        ])->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.product_id', $rice->id)
            ->assertJsonPath('items.0.quantity', 1);
    }

    public function test_unmatched_list_ignores_invalid_or_repeated_values(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('generate')->andReturn(json_encode([
                'items' => [],
                'unmatched' => [null, 10, ['name' => 'rice'], ' ', str_repeat('x', 501), ' 2kg red rice ', '2kg red rice'],
            ]));
        });

        $this->actingAs($cashier)->postJson(route('cashier.billing.parse-order'), [
            'text' => '2kg red rice',
        ])->assertOk()->assertJsonPath('unmatched', ['2kg red rice']);
    }

    public function test_rejects_malformed_order_objects(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $answers = [
            '{}', '{"items":[]}', '{"items":"rice","unmatched":[]}', '{"items":[],"unmatched":"rice"}',
            '{"items":{},"unmatched":[]}', '{"items":[],"unmatched":{}}',
        ];

        $this->mock(AiService::class, function ($mock) use ($answers) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('generate')->andReturn(...$answers);
        });

        foreach ($answers as $answer) {
            $response = $this->actingAs($cashier)->postJson(route('cashier.billing.parse-order'), [
                'text' => 'rice',
            ]);

            $response->assertOk()->assertJsonPath('items', [])->assertJsonPath('unmatched', []);
            $this->assertNotEmpty($response->json('error'));
        }
    }

    public function test_unavailable_ai_returns_empty_lists_and_a_manual_entry_error(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('generate')->andReturn(null);
        });

        $response = $this->actingAs($cashier)->postJson(route('cashier.billing.parse-order'), [
            'text' => '2 rice',
        ]);

        $response->assertOk()->assertJsonPath('items', [])->assertJsonPath('unmatched', []);
        $this->assertNotEmpty($response->json('error'));
    }

    private function product(array $values = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Rice 1kg', 'sku' => 'RICE', 'cost_price' => 200, 'selling_price' => 250,
            'stock_qty' => 10, 'reorder_level' => 5,
        ], $values));
    }
}
