<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PromotionPosterController;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PromotionCreateDraftPosterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function product(): Product
    {
        return Product::create([
            'name' => 'Rice 5kg', 'sku' => 'SKU-DRAFT-'.uniqid(),
            'cost_price' => 1000, 'selling_price' => 1500, 'stock_qty' => 50, 'reorder_level' => 5,
        ]);
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

    public function test_cashier_is_forbidden_from_draft_poster_routes(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $product = $this->product();

        $this->actingAs($cashier)->postJson(route('admin.promotions.poster.generate-draft'), [
            'product_id' => $product->id, 'title' => 'x', 'offer_price' => 1000,
        ])->assertForbidden();
        $this->actingAs($cashier)->postJson(route('admin.promotions.poster.discard-draft'))->assertForbidden();
    }

    public function test_generate_draft_stores_in_session_not_database(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $product = $this->product();

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('generateImage')->once()->andReturn($this->fakeJpegBytes());
        });

        $response = $this->actingAs($admin)->postJson(route('admin.promotions.poster.generate-draft'), [
            'product_id' => $product->id,
            'title' => 'Weekend Rice Sale',
            'offer_price' => 1200,
            'description' => 'Great deal',
        ]);

        $response->assertOk();
        $response->assertJson(['used_ai' => true]);

        $this->assertSame(0, Promotion::count(), 'generating a draft must not create any promotion row');

        $draft = session(PromotionPosterController::DRAFT_SESSION_KEY);
        $this->assertNotNull($draft);
        Storage::disk('public')->assertExists($draft['path']);
        $this->assertStringStartsWith('promotions/pending/draft-', $draft['path']);
    }

    public function test_generate_draft_falls_back_to_placeholder_when_ai_unavailable(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $product = $this->product();

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('generateImage')->once()->andReturn(null);
        });

        $response = $this->actingAs($admin)->postJson(route('admin.promotions.poster.generate-draft'), [
            'product_id' => $product->id, 'title' => 'x', 'offer_price' => 1000,
        ]);

        $response->assertOk();
        $response->assertJson(['used_ai' => false]);
    }

    public function test_discard_draft_deletes_file_and_clears_session(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $product = $this->product();

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('generateImage')->once()->andReturn($this->fakeJpegBytes());
        });
        $this->actingAs($admin)->postJson(route('admin.promotions.poster.generate-draft'), [
            'product_id' => $product->id, 'title' => 'x', 'offer_price' => 1000,
        ]);
        $path = session(PromotionPosterController::DRAFT_SESSION_KEY)['path'];

        $response = $this->actingAs($admin)->postJson(route('admin.promotions.poster.discard-draft'));

        $response->assertOk();
        Storage::disk('public')->assertMissing($path);
        $this->assertNull(session(PromotionPosterController::DRAFT_SESSION_KEY));
    }

    public function test_creating_a_promotion_with_use_generated_poster_links_the_draft_image(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $product = $this->product();

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('generateImage')->once()->andReturn($this->fakeJpegBytes());
        });
        $this->actingAs($admin)->postJson(route('admin.promotions.poster.generate-draft'), [
            'product_id' => $product->id, 'title' => 'Weekend Rice Sale', 'offer_price' => 1200,
        ])->assertOk();
        $draftPath = session(PromotionPosterController::DRAFT_SESSION_KEY)['path'];

        $response = $this->actingAs($admin)->post(route('admin.promotions.store'), [
            'title' => 'Weekend Rice Sale',
            'product_id' => $product->id,
            'offer_price' => 1200,
            'start_date' => now(),
            'end_date' => now()->addDay(),
            'display_duration' => 10,
            'priority' => 'normal',
            'target_screen' => 'customer_display',
            'use_generated_poster' => '1',
        ]);

        $response->assertRedirect(route('admin.promotions.index'));

        $promotion = Promotion::firstOrFail();
        $this->assertSame('ai', $promotion->poster_source);
        $this->assertNotNull($promotion->poster_path);
        $this->assertStringStartsWith('promotions/', $promotion->poster_path);
        $this->assertStringContainsString('pending', $draftPath); // sanity: it really did move out of pending/
        $this->assertStringNotContainsString('pending', $promotion->poster_path);
        Storage::disk('public')->assertExists($promotion->poster_path);
        Storage::disk('public')->assertMissing($draftPath);
        $this->assertNotEmpty($promotion->ai_generations);

        // The session draft's lifecycle ends with this create attempt either way.
        $this->assertNull(session(PromotionPosterController::DRAFT_SESSION_KEY));
    }

    public function test_creating_a_promotion_without_using_the_draft_discards_it(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $product = $this->product();

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('generateImage')->once()->andReturn($this->fakeJpegBytes());
        });
        $this->actingAs($admin)->postJson(route('admin.promotions.poster.generate-draft'), [
            'product_id' => $product->id, 'title' => 'x', 'offer_price' => 1000,
        ])->assertOk();
        $draftPath = session(PromotionPosterController::DRAFT_SESSION_KEY)['path'];

        // Note: no use_generated_poster field at all — admin generated a
        // preview but never clicked "Use This Poster".
        $this->actingAs($admin)->post(route('admin.promotions.store'), [
            'title' => 'No Poster Promo',
            'product_id' => $product->id,
            'offer_price' => 1000,
            'start_date' => now(),
            'end_date' => now()->addDay(),
            'display_duration' => 10,
            'priority' => 'normal',
            'target_screen' => 'customer_display',
        ])->assertRedirect();

        $promotion = Promotion::firstOrFail();
        $this->assertNull($promotion->poster_path);
        Storage::disk('public')->assertMissing($draftPath);
        $this->assertNull(session(PromotionPosterController::DRAFT_SESSION_KEY));
    }

    public function test_manual_upload_takes_precedence_over_a_pending_draft(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $product = $this->product();

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('generateImage')->once()->andReturn($this->fakeJpegBytes());
        });
        $this->actingAs($admin)->postJson(route('admin.promotions.poster.generate-draft'), [
            'product_id' => $product->id, 'title' => 'x', 'offer_price' => 1000,
        ])->assertOk();

        $upload = \Illuminate\Http\UploadedFile::fake()->image('custom.jpg');

        $this->actingAs($admin)->post(route('admin.promotions.store'), [
            'title' => 'Custom Upload Promo',
            'product_id' => $product->id,
            'offer_price' => 1000,
            'start_date' => now(),
            'end_date' => now()->addDay(),
            'display_duration' => 10,
            'priority' => 'normal',
            'target_screen' => 'customer_display',
            'use_generated_poster' => '1',
            'poster_image' => $upload,
        ])->assertRedirect();

        $promotion = Promotion::firstOrFail();
        $this->assertSame('custom', $promotion->poster_source);
    }
}
