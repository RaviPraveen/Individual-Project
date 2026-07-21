<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PromotionPosterTest extends TestCase
{
    use RefreshDatabase;

    private function promotion(): Promotion
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Rice 5kg', 'sku' => 'SKU-POSTER-'.uniqid(),
            'cost_price' => 1000, 'selling_price' => 1500, 'stock_qty' => 50, 'reorder_level' => 5,
        ]);

        return Promotion::create([
            'title' => 'Poster Test Promo', 'product_id' => $product->id,
            'current_price' => 1500, 'offer_price' => 1200, 'discount_percentage' => 20,
            'start_date' => now(), 'end_date' => now()->addDay(),
            'display_duration' => 10, 'priority' => 'normal', 'status' => Promotion::STATUS_ACTIVE,
            'target_screen' => 'customer_display', 'created_by' => $admin->id,
        ]);
    }

    private function admin(): User
    {
        return User::first() ?? User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function fakeJpegBytes(): string
    {
        $image = imagecreatetruecolor(20, 20);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    public function test_cashier_is_forbidden_from_poster_generation_routes(): void
    {
        $promotion = $this->promotion();
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $this->actingAs($cashier)->post(route('admin.promotions.poster.generate', $promotion))->assertForbidden();
        $this->actingAs($cashier)->post(route('admin.promotions.poster.approve', $promotion))->assertForbidden();
        $this->actingAs($cashier)->post(route('admin.promotions.poster.discard', $promotion))->assertForbidden();
    }

    public function test_generate_composites_a_poster_from_the_ai_background_and_records_history(): void
    {
        Storage::fake('public');
        $promotion = $this->promotion();
        $admin = User::whereKey($promotion->created_by)->first();

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('generateImage')->once()->andReturn($this->fakeJpegBytes());
        });

        $response = $this->actingAs($admin)->postJson(route('admin.promotions.poster.generate', $promotion));

        $response->assertOk();
        $response->assertJson(['used_ai' => true]);

        $promotion->refresh();
        $this->assertNotNull($promotion->pending_poster_path);
        $this->assertTrue($promotion->pending_poster_used_ai);
        Storage::disk('public')->assertExists($promotion->pending_poster_path);
        $this->assertCount(1, $promotion->ai_generations);
        $this->assertNull($promotion->poster_path, 'poster_path must stay untouched until approved');
    }

    public function test_generate_falls_back_to_a_placeholder_when_ai_is_unavailable(): void
    {
        Storage::fake('public');
        $promotion = $this->promotion();
        $admin = User::whereKey($promotion->created_by)->first();

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('generateImage')->once()->andReturn(null);
        });

        $response = $this->actingAs($admin)->postJson(route('admin.promotions.poster.generate', $promotion));

        $response->assertOk();
        $response->assertJson(['used_ai' => false]);

        $promotion->refresh();
        $this->assertNotNull($promotion->pending_poster_path);
        $this->assertFalse($promotion->pending_poster_used_ai);
        Storage::disk('public')->assertExists($promotion->pending_poster_path);
    }

    public function test_approve_promotes_the_pending_poster_to_live_and_clears_pending(): void
    {
        Storage::fake('public');
        $promotion = $this->promotion();
        $admin = User::whereKey($promotion->created_by)->first();

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('generateImage')->once()->andReturn($this->fakeJpegBytes());
        });
        $this->actingAs($admin)->postJson(route('admin.promotions.poster.generate', $promotion));
        $promotion->refresh();
        $pendingPath = $promotion->pending_poster_path;

        $response = $this->actingAs($admin)->postJson(route('admin.promotions.poster.approve', $promotion));

        $response->assertOk();
        $promotion->refresh();
        $this->assertSame($pendingPath, $promotion->poster_path);
        $this->assertSame('ai', $promotion->poster_source);
        $this->assertNull($promotion->pending_poster_path);
    }

    public function test_approve_without_a_pending_poster_is_rejected(): void
    {
        $promotion = $this->promotion();
        $admin = User::whereKey($promotion->created_by)->first();

        $response = $this->actingAs($admin)->postJson(route('admin.promotions.poster.approve', $promotion));

        $response->assertStatus(422);
        $this->assertNull($promotion->fresh()->poster_path);
    }

    public function test_discard_deletes_the_pending_poster_file_and_clears_the_field(): void
    {
        Storage::fake('public');
        $promotion = $this->promotion();
        $admin = User::whereKey($promotion->created_by)->first();

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('generateImage')->once()->andReturn($this->fakeJpegBytes());
        });
        $this->actingAs($admin)->postJson(route('admin.promotions.poster.generate', $promotion));
        $promotion->refresh();
        $pendingPath = $promotion->pending_poster_path;

        $response = $this->actingAs($admin)->postJson(route('admin.promotions.poster.discard', $promotion));

        $response->assertOk();
        Storage::disk('public')->assertMissing($pendingPath);
        $this->assertNull($promotion->fresh()->pending_poster_path);
        $this->assertNull($promotion->fresh()->poster_path, 'discarding must never touch the live poster');
    }
}
