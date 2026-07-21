<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CustomerDisplayController extends Controller
{
    private const TTL_ACTIVE = 300; // keep an in-progress cart alive for 5 minutes of inactivity

    public function show(): View
    {
        return view('cashier.display.show');
    }

    public function data(Request $request): JsonResponse
    {
        $state = Cache::get($this->cacheKey($request->user()->id));

        return response()->json($state ?? ['status' => 'idle']);
    }

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['present', 'array'],
            'items.*.name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer'],
            'items.*.unit_price' => ['required', 'numeric'],
            'items.*.line_total' => ['required', 'numeric'],
            'subtotal' => ['required', 'numeric'],
            'discount' => ['required', 'numeric'],
            'tax' => ['required', 'numeric'],
            'bag_fee' => ['nullable', 'numeric'],
            'total' => ['required', 'numeric'],
            'customer_name' => ['nullable', 'string'],
            'points_balance' => ['nullable', 'integer'],
            'points_preview' => ['nullable', 'integer'],
        ]);

        $status = count($validated['items']) > 0 ? 'active' : 'idle';

        Cache::put(
            $this->cacheKey($request->user()->id),
            array_merge($validated, ['status' => $status]),
            self::TTL_ACTIVE
        );

        return response()->json(['ok' => true]);
    }

    /**
     * The right-panel rotation feed. Ordering (priority, then featured
     * first) is a tie-break for promotions with equal claim to airtime —
     * the actual "featured shows more often" behavior is achieved
     * client-side by repeating featured entries in the rotation playlist,
     * since JSON ordering alone can't express repetition.
     */
    public function promotions(): JsonResponse
    {
        Promotion::syncDueStatuses();

        $promotions = Promotion::visibleOnDisplay('customer_display')
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'normal' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'promotions' => $promotions->map(fn (Promotion $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'description' => $p->description,
                'poster_url' => $p->poster_path ? Storage::disk('public')->url($p->poster_path) : null,
                'current_price' => (float) $p->current_price,
                'offer_price' => (float) $p->offer_price,
                'discount_percentage' => (float) $p->discount_percentage,
                'display_duration' => max(5, (int) $p->display_duration),
                'is_featured' => (bool) $p->is_featured,
            ])->values(),
        ]);
    }

    public function markPromotionViewed(Promotion $promotion): JsonResponse
    {
        $promotion->increment('display_count');

        return response()->json(['ok' => true]);
    }

    private function cacheKey(int $cashierId): string
    {
        return "customer-display:{$cashierId}";
    }
}
