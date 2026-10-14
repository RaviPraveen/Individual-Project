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

    public function __construct(
        private AiService $aiService,
        private PosterComposer $composer,
    ) {}

    // Save a preview. The current poster stays live until this one is approved.
    public function generate(Promotion $promotion): JsonResponse
    {
        $promotion->loadMissing('product.category');

        $prompt = $this->buildPrompt($promotion->product);
        $backgroundBytes = $this->aiService->generateImage($prompt);
        $usedAi = $backgroundBytes !== null;

        // Use a gradient background if Hugging Face cannot generate an image.
        $posterBytes = $this->composer->compose($backgroundBytes ?? '', $promotion);

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
                : __('AI image service is unavailable right now — generated a placeholder poster instead. You can still approve it or try again shortly.'),
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
            'poster_source' => 'ai',
            'pending_poster_path' => null,
            'pending_poster_used_ai' => false,
        ]);

        return response()->json(['message' => __('Poster approved and is now live.')]);
    }

    public function discard(Promotion $promotion): JsonResponse
    {
        if ($promotion->pending_poster_path) {
            Storage::disk('public')->delete($promotion->pending_poster_path);
            $promotion->update(['pending_poster_path' => null, 'pending_poster_used_ai' => false]);
        }

        return response()->json(['message' => __('Generated poster discarded.')]);
    }

    // Remove the published poster; discard() removes only the pending preview.
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

        $prompt = $this->buildPrompt($product);
        $backgroundBytes = $this->aiService->generateImage($prompt);
        $usedAi = $backgroundBytes !== null;
        $posterBytes = $this->composer->compose($backgroundBytes ?? '', $draftPromotion);

        $previous = session(self::DRAFT_SESSION_KEY);
        if ($previous) {
            Storage::disk('public')->delete($previous['path']);
        }

        $path = 'promotions/pending/draft-'.Str::random(12).'.jpg';
        Storage::disk('public')->put($path, $posterBytes);

        session([self::DRAFT_SESSION_KEY => [
            'path' => $path,
            'details' => $draftPromotion->posterDetails(),
            'prompt' => $prompt,
            'used_ai' => $usedAi,
            'created_at' => now()->toIso8601String(),
        ]]);

        return response()->json([
            'poster_url' => Storage::disk('public')->url($path),
            'used_ai' => $usedAi,
            'message' => $usedAi
                ? __('Poster generated.')
                : __('AI image service is unavailable right now — generated a placeholder poster instead. You can still use it or try again shortly.'),
        ]);
    }

    public function discardDraft(): JsonResponse
    {
        $draft = session(self::DRAFT_SESSION_KEY);

        if ($draft) {
            Storage::disk('public')->delete($draft['path']);
            session()->forget(self::DRAFT_SESSION_KEY);
        }

        return response()->json(['message' => __('Generated poster discarded.')]);
    }

    private function buildPrompt(?Product $product): string
    {
        $category = $product?->category?->name ?? 'grocery';

        return "Create a premium supermarket promotional poster background. ".
            "Store: Foodcity. Product: {$product?->name} ({$category}). ".
            "Professional retail product photography, large centered product, ".
            "soft white background with a subtle blue (#3B82F6) brand gradient, ".
            "clean premium commercial look, Instagram-quality lighting, high resolution, ".
            "modern minimal composition. Absolutely no text, no words, no numbers, ".
            "no letters, no logos, no watermark anywhere in the image.";
    }
}
