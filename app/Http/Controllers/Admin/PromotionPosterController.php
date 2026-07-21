<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\AiService;
use App\Services\PosterComposer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PromotionPosterController extends Controller
{
    public function __construct(
        private AiService $ai,
        private PosterComposer $composer,
    ) {}

    /**
     * Generates a new AI poster attempt and stores it as a PENDING preview
     * — poster_path (the LIVE poster shown on the Customer Display) is left
     * untouched until the admin explicitly approves it.
     */
    public function generate(Promotion $promotion): JsonResponse
    {
        $promotion->loadMissing('product.category');

        $prompt = $this->buildPrompt($promotion);
        $backgroundBytes = $this->ai->generateImage($prompt);
        $usedAi = $backgroundBytes !== null;

        // compose() never returns null — it falls back to a brand-gradient
        // background when the AI call fails, so "Generate" always produces
        // a real preview rather than a dead end.
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

    /**
     * Returns JSON rather than back()->with(...) — the edit page updates
     * its DOM and shows a toast directly from this response, so a
     * server-side flash message here would just sit unused in the session
     * and resurface as a stale duplicate toast on the admin's next page.
     */
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

    /**
     * Deliberately asks for a text-free background — see PosterComposer's
     * class docblock for why real price/title text is composited
     * separately rather than trusted to the image model.
     */
    private function buildPrompt(Promotion $promotion): string
    {
        $product = $promotion->product;
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
