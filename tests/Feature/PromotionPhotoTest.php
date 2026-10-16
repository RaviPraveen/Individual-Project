<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PromotionPosterController;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PromotionPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::create([
            'name' => 'Suwandel Rice', 'sku' => 'RICE-PHOTO', 'selling_price' => 1500,
            'cost_price' => 1100, 'stock_qty' => 20, 'reorder_level' => 5,
        ]);
    }

    private function photo(string $name = 'rice.png', bool $red = true): UploadedFile
    {
        $image = imagecreatetruecolor(100, 200);
        imagefill($image, 0, 0, imagecolorallocate($image, $red ? 230 : 20, 20, $red ? 20 : 230));
        imagefilledrectangle($image, 0, 0, 99, 9, imagecolorallocate($image, 20, 230, 20));
        imagefilledrectangle($image, 0, 190, 99, 199, imagecolorallocate($image, 20, 20, 230));
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    private function details(Product $product): array
    {
        return [
            'product_id' => $product->id, 'title' => 'Suwandel Rice Offer', 'offer_price' => 1200,
            'start_date' => now()->subHour()->format('Y-m-d\\TH:i'),
            'end_date' => now()->addDay()->format('Y-m-d\\TH:i'),
            'display_duration' => 10, 'priority' => 'normal', 'target_screen' => 'customer_display',
        ];
    }

    public function test_uploaded_product_is_kept_in_draft_and_saved_poster_when_ai_is_unavailable(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $product = $this->product();
        $photo = $this->photo();
        $this->mock(AiService::class)->shouldReceive('generateImage')->once()
            ->withArgs(fn ($prompt) => str_contains($prompt, 'No products'))
            ->andReturn(null);

        $this->actingAs($admin)->post(route('admin.promotions.poster.generate-draft'), [
            'product_id' => $product->id, 'title' => 'Suwandel Rice Offer',
            'offer_price' => 1200, 'product_photo' => $photo,
        ])->assertOk()->assertJsonPath('used_ai', false);

        $draft = session(PromotionPosterController::DRAFT_SESSION_KEY);
        Storage::disk('public')->assertExists($draft['photo_path']);
        $image = imagecreatefromstring(Storage::disk('public')->get($draft['path']));
        $center = imagecolorsforindex($image, imagecolorat($image, 600, 325));
        $top = imagecolorsforindex($image, imagecolorat($image, 600, 120));
        $bottom = imagecolorsforindex($image, imagecolorat($image, 600, 530));
        $this->assertGreaterThan(200, $center['red']);
        $this->assertLessThan(50, $center['blue']);
        $this->assertGreaterThan(200, $top['green']);
        $this->assertGreaterThan(200, $bottom['blue']);
        imagedestroy($image);

        $this->actingAs($admin)->post(route('admin.promotions.store'), array_merge($this->details($product), [
            'use_generated_poster' => 1, 'product_photo' => $photo,
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.promotions.index'));
        $promotion = Promotion::firstOrFail();
        $this->assertSame('template', $promotion->poster_source);
        $this->assertSame('Template Poster', $promotion->posterLabel());
        $this->assertSame($draft['photo_path'], $promotion->product_photo_path);
        Storage::disk('public')->assertExists($promotion->poster_path);
        Storage::disk('public')->assertMissing($draft['path']);
    }

    public function test_changing_photo_cannot_save_an_old_draft(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $product = $this->product();
        $this->mock(AiService::class)->shouldReceive('generateImage')->once()->andReturn(null);
        $this->actingAs($admin)->post(route('admin.promotions.poster.generate-draft'), [
            'product_id' => $product->id, 'title' => 'Suwandel Rice Offer',
            'offer_price' => 1200, 'product_photo' => $this->photo(),
        ])->assertOk();
        $this->post(route('admin.promotions.store'), array_merge($this->details($product), [
            'use_generated_poster' => 1, 'product_photo' => $this->photo('other.png', false),
        ]))->assertSessionHasErrors('poster_image');
        $this->assertSame(0, Promotion::count());
    }

    public function test_discard_removes_draft_photo_and_poster(): void
    {
        Storage::fake('public');
        $product = $this->product();
        $this->mock(AiService::class)->shouldReceive('generateImage')->once()->andReturn(null);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]))
            ->post(route('admin.promotions.poster.generate-draft'), [
                'product_id' => $product->id, 'title' => 'Rice', 'offer_price' => 1200,
                'product_photo' => $this->photo(),
            ])->assertOk();
        $draft = session(PromotionPosterController::DRAFT_SESSION_KEY);
        $this->postJson(route('admin.promotions.poster.discard-draft'))->assertOk();
        Storage::disk('public')->assertMissing($draft['path']);
        Storage::disk('public')->assertMissing($draft['photo_path']);
    }

    public function test_saved_photo_is_used_for_regeneration_and_can_be_removed(): void
    {
        Storage::fake('public');
        $product = $this->product();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]))->post(route('admin.promotions.store'),
            array_merge($this->details($product), ['product_photo' => $this->photo()]))->assertSessionHasNoErrors();
        $promotion = Promotion::firstOrFail();
        $path = $promotion->product_photo_path;
        $this->mock(AiService::class)->shouldReceive('generateImage')->once()->andReturn(null);
        $this->postJson(route('admin.promotions.poster.generate', $promotion))->assertOk();
        $pending = $promotion->fresh()->pending_poster_path;
        $this->postJson(route('admin.promotions.poster.approve', $promotion))->assertOk();
        $this->patch(route('admin.promotions.update', $promotion),
            array_merge($this->details($product), ['remove_photo' => 1]))->assertSessionHasNoErrors();
        $this->assertNull($promotion->fresh()->product_photo_path);
        $this->assertNull($promotion->fresh()->poster_path);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertMissing($pending);
    }

    public function test_generation_rejects_a_poster_if_details_change_during_the_ai_request(): void
    {
        Storage::fake('public');
        $product = $this->product();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]))
            ->post(route('admin.promotions.store'), $this->details($product))->assertSessionHasNoErrors();
        $promotion = Promotion::firstOrFail();
        $this->mock(AiService::class)->shouldReceive('generateImage')->once()->andReturnUsing(function () use ($promotion) {
            Promotion::whereKey($promotion->id)->update(['title' => 'New rice offer']);

            return null;
        });
        $this->postJson(route('admin.promotions.poster.generate', $promotion))->assertStatus(422);
        $this->assertNull($promotion->fresh()->pending_poster_path);
        $this->assertSame([], Storage::disk('public')->files('promotions/pending'));
    }

    public function test_invalid_and_oversized_photos_are_rejected_before_ai_runs(): void
    {
        $product = $this->product();
        $this->mock(AiService::class)->shouldNotReceive('generateImage');
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        foreach ([UploadedFile::fake()->create('rice.txt', 1), UploadedFile::fake()->image('big.jpg', 4097, 20)] as $photo) {
            $this->post(route('admin.promotions.poster.generate-draft'), [
                'product_id' => $product->id, 'title' => 'Rice', 'offer_price' => 1200, 'product_photo' => $photo,
            ])->assertSessionHasErrors('product_photo');
        }
    }

    public function test_deleting_promotion_removes_its_source_photo(): void
    {
        Storage::fake('public');
        $product = $this->product();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]))->post(route('admin.promotions.store'),
            array_merge($this->details($product), ['product_photo' => $this->photo()]))->assertSessionHasNoErrors();
        $promotion = Promotion::firstOrFail();
        $path = $promotion->product_photo_path;
        $this->delete(route('admin.promotions.destroy', $promotion))->assertRedirect();
        Storage::disk('public')->assertMissing($path);
    }
}
