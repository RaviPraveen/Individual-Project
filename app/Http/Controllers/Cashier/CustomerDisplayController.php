<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

    private function cacheKey(int $cashierId): string
    {
        return "customer-display:{$cashierId}";
    }
}
