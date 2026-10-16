<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\AiService;
use App\Services\PosterComposer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PromotionPosterController extends Controller
{
    // Keep one unsaved poster per admin session; do not accept a file path from the client.
    public const DRAFT_SESSION_KEY = 'promotion_draft_poster';

    public const PHOTO_RULES = ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'];

    public function __construct(
        private AiService $aiService,
        private PosterComposer $composer,
    ) {}

    // Save a preview. The current poster stays live until this one is approved.
    public function generate(Promotion $promotion): JsonResponse
    {
        $promotion->loadMissing('product.category');
        $details = $promotion->posterDetails();

        $photo = $promotion->product_photo_path && Storage::disk('public')->exists($promotion->product_photo_path)
            ? Storage::disk('public')->get($promotion->product_photo_path) : null;
        $prompt = $this->buildPrompt($promotion->product, $photo !== null);
        $backgroundBytes = $this->aiService->generateImage($prompt);
        $usedAi = $backgroundBytes !== null;

        $posterBytes = $this->composer->compose($backgroundBytes ?? '', $promotion, $photo);

        if ($promotion->refresh()->posterDetails() !== $details) {
            return response()->json(['message' => __('The promotion changed while generating. Generate a new poster.')], 422);
        }

        if ($promotion->pending_poster_path) {
            Storage::disk('public')->delete($promotion->pending_poster_path);
        }

        $path = 'promotions/pending/'.$promotion->id.'-'.Str::random(8).'.jpg';
        Storage::disk('public')->put($path, $posterBytes);

        $history = $promotion->ai_generations ?? [];
        $history[] = [
            'path' => $path,
            'prompt' => $prompt,
            'used_ai' => $usedAi,
            'created_at' => now()->toIso8601String(),
        ];

        $promotion->update([
            'pending_poster_path' => $path,
            'pending_poster_used_ai' => $usedAi,
            'ai_generations' => $history,
        ]);

        return response()->json([
            'poster_url' => Storage::disk('public')->url($path),
            'used_ai' => $usedAi,
            'message' => $usedAi
                ? __('Poster generated.')
                : ($photo ? __('The AI background is unavailable. Your product photo was kept on a plain background.')
                    : __('AI image service is unavailable right now — generated a placeholder poster instead. You can still approve it or try again shortly.')),
        ]);
    }

    public function approve(Promotion $promotion): JsonResponse
    {
        if (! $promotion->pending_poster_path) {
            return response()->json(['message' => __('There is no generated poster waiting for approval.')], 422);
        }

        if ($promotion->poster_path && $promotion->poster_path !== $promotion->pending_poster_path) {
            Storage::disk('public')->delete($promotion->poster_path);
        }

        $promotion->update([
            'poster_path' => $promotion->pending_poster_path,
            'poster_source' => $promotion->pending_poster_used_ai ? 'ai' : 'template',
            'pending_poster_path' => null,
            'pending_poster_used_ai' => false,
        ]);

        return response()->json([
            'message' => __('Poster approved and is now live.'),
            'poster_label' => __($promotion->posterLabel()),
        ]);
    }

    public function discard(Promotion $promotion): JsonResponse
    {
        if ($promotion->pending_poster_path) {
            Storage::disk('public')->delete($promotion->pending_poster_path);
            $promotion->update(['pending_poster_path' => null, 'pending_poster_used_ai' => false]);
        }

        return response()->json(['message' => __('Generated poster discarded.')]);
    }

    public function destroyLive(Promotion $promotion): JsonResponse
    {
        if ($promotion->poster_path) {
            Storage::disk('public')->delete($promotion->poster_path);
            $promotion->update(['poster_path' => null, 'poster_source' => null]);
        }

        return response()->json(['message' => __('Live poster deleted.')]);
    }

    // Store a preview in the session until the new promotion is saved.
    public function generateDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'title' => ['required', 'string', 'max:255'],
            'offer_price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'product_photo' => self::PHOTO_RULES,
            'remove_photo' => ['nullable', 'boolean'],
        ]);

        $product = Product::with('category')->findOrFail($validated['product_id']);

        $draftPromotion = new Promotion([
            'product_id' => $product->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'current_price' => $product->selling_price,
            'offer_price' => $validated['offer_price'],
        ]);
        $draftPromotion->refreshDiscountPercentage();

        $previous = session(self::DRAFT_SESSION_KEY);
        $photoPath = null;
        $photoHash = null;
        if ($request->hasFile('product_photo')) {
            $photoPath = $request->file('product_photo')->store('promotions/photos', 'public');
            $photoHash = hash_file('sha256', $request->file('product_photo')->getRealPath());
        } elseif (! $request->boolean('remove_photo') && ($previous['details']['product_id'] ?? null) === $product->id
            && ! empty($previous['photo_path']) && Storage::disk('public')->exists($previous['photo_path'])) {
            $photoPath = $previous['photo_path'];
            $photoHash = $previous['photo_hash'] ?? null;
        }
        $draftPromotion->product_photo_path = $photoPath;
        $photo = $photoPath ? Storage::disk('public')->get($photoPath) : null;
        $prompt = $this->buildPrompt($product, $photo !== null);
        $backgroundBytes = $this->aiService->generateImage($prompt);
        $usedAi = $backgroundBytes !== null;
        $posterBytes = $this->composer->compose($backgroundBytes ?? '', $draftPromotion, $photo);

        if ($previous) {
            Storage::disk('public')->delete($previous['path']);
            if (! empty($previous['photo_path']) && $previous['photo_path'] !== $photoPath) {
                Storage::disk('public')->delete($previous['photo_path']);
            }
        }

        $path = 'promotions/pending/draft-'.Str::random(12).'.jpg';
        Storage::disk('public')->put($path, $posterBytes);

        session([self::DRAFT_SESSION_KEY => [
            'path' => $path,
            'details' => $draftPromotion->posterDetails(),
            'prompt' => $prompt,
            'used_ai' => $usedAi,
            'photo_path' => $photoPath,
            'photo_hash' => $photoHash,
            'created_at' => now()->toIso8601String(),
        ]]);

        return response()->json([
            'poster_url' => Storage::disk('public')->url($path),
            'photo_url' => $photoPath ? Storage::disk('public')->url($photoPath) : null,
            'used_ai' => $usedAi,
            'message' => $usedAi
                ? __('Poster generated.')
                : ($photo ? __('The AI background is unavailable. Your product photo was kept on a plain background.')
                    : __('AI image service is unavailable right now — generated a placeholder poster instead. You can still use it or try again shortly.')),
        ]);
    }

    public function discardDraft(): JsonResponse
    {
        $draft = session(self::DRAFT_SESSION_KEY);

        if ($draft) {
            Storage::disk('public')->delete(array_filter([$draft['path'], $draft['photo_path'] ?? null]));
            session()->forget(self::DRAFT_SESSION_KEY);
        }

        return response()->json(['message' => __('Generated poster discarded.')]);
    }

    private function buildPrompt(?Product $product, bool $hasPhoto = false): string
    {
        $category = $product?->category?->name ?? 'grocery';

        if ($hasPhoto) {
            return 'Create an empty supermarket advertising background with a soft white and blue gradient. '.
                'Leave a large clean space in the center for a product photo that will be added later. '.
                'No products, no food, no packaging, no text, no numbers, no logos, no watermark.';
        }

        return "Create a premium supermarket promotional poster background. ".
            "Store: Foodcity. Product: {$product?->name} ({$category}). ".
            "Professional retail product photography, large centered product, ".
            "soft white background with a subtle blue (#3B82F6) brand gradient, ".
            "clean premium commercial look, Instagram-quality lighting, high resolution, ".
            "modern minimal composition. Absolutely no text, no words, no numbers, ".
            "no letters, no logos, no watermark anywhere in the image.";
    }
}
