<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiLog;
use App\Models\Product;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatTest extends TestCase
{
    use RefreshDatabase;

    private function mockAi(string $response = 'Here is your answer.'): void
    {
        $this->mock(AiService::class, function ($mock) use ($response) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('generate')->andReturn($response);
        });
    }

    public function test_index_loads_for_admin_and_cashier(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.ai-chat.index'))->assertOk();
        $this->actingAs($cashier)->get(route('cashier.ai-chat.index'))->assertOk();
    }

    public function test_ask_creates_conversation_lazily_and_logs_the_exchange(): void
    {
        $this->mockAi();
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $response = $this->actingAs($cashier)->postJson(route('cashier.ai-chat.ask'), [
            'message' => 'Which products need restocking?',
        ]);

        $response->assertOk();
        $conversationId = $response->json('conversation_id');

        $this->assertDatabaseHas('ai_conversations', ['id' => $conversationId, 'user_id' => $cashier->id]);
        $this->assertDatabaseHas('ai_logs', [
            'conversation_id' => $conversationId,
            'query' => 'Which products need restocking?',
            'response' => 'Here is your answer.',
        ]);

        // A second message in the same conversation must not create a new one.
        $second = $this->actingAs($cashier)->postJson(route('cashier.ai-chat.ask'), [
            'message' => 'And what about milk?',
            'conversation_id' => $conversationId,
        ]);

        $this->assertEquals($conversationId, $second->json('conversation_id'));
        $this->assertEquals(1, AiConversation::where('user_id', $cashier->id)->count());
    }

    public function test_products_widget_reflects_real_low_stock_data(): void
    {
        $this->mockAi();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        Product::create([
            'name' => 'Rice 5kg', 'sku' => 'SKU-RICE', 'cost_price' => 900, 'selling_price' => 1200,
            'stock_qty' => 2, 'reorder_level' => 5,
        ]);
        Product::create([
            'name' => 'Sugar 1kg', 'sku' => 'SKU-SUGAR', 'cost_price' => 200, 'selling_price' => 280,
            'stock_qty' => 50, 'reorder_level' => 10,
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.ai-chat.ask'), [
            'message' => 'Which products need restocking?',
        ]);

        $widget = $response->json('message.widget');

        $this->assertEquals('products', $widget['type']);
        $this->assertCount(1, $widget['rows']);
        $this->assertEquals('Rice 5kg', $widget['rows'][0]['name']);
        $this->assertEquals('Low Stock', $widget['rows'][0]['status']);
    }

    public function test_switch_conversation_is_scoped_to_owner(): void
    {
        $this->mockAi();
        $owner = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $intruder = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $conversationId = $this->actingAs($owner)->postJson(route('cashier.ai-chat.ask'), [
            'message' => 'Hello',
        ])->json('conversation_id');

        $this->actingAs($owner)->getJson(route('cashier.ai-chat.conversations.switch', $conversationId))->assertOk();
        $this->actingAs($intruder)->getJson(route('cashier.ai-chat.conversations.switch', $conversationId))->assertForbidden();
    }

    public function test_rename_delete_and_clear_conversation(): void
    {
        $this->mockAi();
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $conversationId = $this->actingAs($cashier)->postJson(route('cashier.ai-chat.ask'), [
            'message' => 'Hello there',
        ])->json('conversation_id');

        $this->actingAs($cashier)->patchJson(route('cashier.ai-chat.conversations.rename', $conversationId), [
            'title' => 'My renamed chat',
        ])->assertOk()->assertJson(['title' => 'My renamed chat']);

        $this->assertDatabaseHas('ai_conversations', ['id' => $conversationId, 'title' => 'My renamed chat']);

        $this->actingAs($cashier)->deleteJson(route('cashier.ai-chat.conversations.clear', $conversationId))->assertOk();
        $this->assertDatabaseCount('ai_logs', 0);
        $this->assertDatabaseHas('ai_conversations', ['id' => $conversationId, 'title' => null]);

        $this->actingAs($cashier)->deleteJson(route('cashier.ai-chat.conversations.delete', $conversationId))->assertOk();
        $this->assertDatabaseMissing('ai_conversations', ['id' => $conversationId]);
    }

    public function test_feedback_can_be_set_and_cleared(): void
    {
        $this->mockAi();
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $logId = $this->actingAs($cashier)->postJson(route('cashier.ai-chat.ask'), [
            'message' => 'Hello',
        ])->json('message.id');

        $this->actingAs($cashier)->patchJson(route('cashier.ai-chat.messages.feedback', $logId), [
            'feedback' => 'like',
        ])->assertOk();

        $this->assertDatabaseHas('ai_logs', ['id' => $logId, 'feedback' => 'like']);

        $this->actingAs($cashier)->patchJson(route('cashier.ai-chat.messages.feedback', $logId), [
            'feedback' => null,
        ])->assertOk();

        $this->assertDatabaseHas('ai_logs', ['id' => $logId, 'feedback' => null]);
    }
}
