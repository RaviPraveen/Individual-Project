<?php

namespace App\Services;

use App\Models\AiLog;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\SaleItem;
use Illuminate\Support\Facades\Cache;

/**
 * Same narrative-with-fallback shape as ReportController's dead-stock/profit
 * narratives: compute real signals in PHP first (nothing here is invented
 * by the model), then ask the AI to phrase them as short recommendations.
 * Falls back to the plain computed bullet list if the AI is unconfigured
 * or unreachable, so the panel is never empty just because AI is down.
 */
class PromotionRecommendationService
{
    public function __construct(private AiService $ai) {}

    public function recommendations(?int $userId = null): array
    {
        return Cache::remember(
            'promotion_recommendations_'.intdiv(now()->minute, 30).'_'.now()->format('YmdH'),
            now()->addMinutes(30),
            fn () => $this->build($userId),
        );
    }

    private function build(?int $userId): array
    {
        $signals = $this->collectSignals();

        if (empty($signals)) {
            return [];
        }

        $lines = collect($signals)->map(fn ($s) => "- {$s['reason']} → {$s['suggestion']}")->implode("\n");
        $narrative = null;

        if ($this->ai->isConfigured()) {
            $prompt = 'You are a retail marketing analyst for a Sri Lankan supermarket called Welcome Foodcity. '.
                "Based ONLY on the following computed signals, rewrite each one as a single short, punchy promotion ".
                "recommendation (one line each, same order, no numbering, no extra commentary). Do not invent any ".
                "products or numbers beyond what is given.\n\n{$lines}";

            $narrative = $this->ai->generate($prompt);

            if ($userId) {
                AiLog::create([
                    'user_id' => $userId,
                    'query' => 'Promotion Manager AI recommendations ('.now()->format('Y-m-d H:i').')',
                    'response' => $narrative ?? '[AI unavailable — using computed signals as-is]',
                ]);
            }
        }

        if ($narrative) {
            $phrased = array_values(array_filter(array_map('trim', explode("\n", $narrative))));

            foreach ($signals as $i => &$signal) {
                $signal['text'] = $phrased[$i] ?? "{$signal['reason']} — {$signal['suggestion']}";
            }
        } else {
            foreach ($signals as &$signal) {
                $signal['text'] = "{$signal['reason']} — {$signal['suggestion']}";
            }
        }

        return $signals;
    }

    /**
     * @return array<int, array{icon: string, reason: string, suggestion: string, product_id: ?int}>
     */
    private function collectSignals(): array
    {
        $signals = [];
        $start = now()->subDays(30)->startOfDay();
        $end = now()->endOfDay();

        $salesLast30 = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.created_at', [$start, $end])
            ->selectRaw('sale_items.product_id, SUM(sale_items.quantity) as qty_sold')
            ->groupBy('sale_items.product_id')
            ->pluck('qty_sold', 'product_id');

        $prevStart = now()->subDays(60)->startOfDay();
        $prevEnd = now()->subDays(30)->startOfDay();
        $salesPrev30 = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.created_at', [$prevStart, $prevEnd])
            ->selectRaw('sale_items.product_id, SUM(sale_items.quantity) as qty_sold')
            ->groupBy('sale_items.product_id')
            ->pluck('qty_sold', 'product_id');

        $promotedProductIds = Promotion::whereIn('status', [Promotion::STATUS_ACTIVE, Promotion::STATUS_SCHEDULED])
            ->pluck('product_id')->all();

        // High stock, barely selling — a straightforward discount candidate.
        $highStockSlowMover = Product::where('is_active', true)
            ->whereNotIn('id', $promotedProductIds)
            ->get()
            ->filter(fn (Product $p) => $p->stock_qty > ($p->reorder_level * 3) && ($salesLast30[$p->id] ?? 0) <= 3)
            ->sortByDesc('stock_qty')
            ->first();

        if ($highStockSlowMover) {
            $signals[] = [
                'icon' => 'bi-box-seam',
                'reason' => "{$highStockSlowMover->name} stock is high ({$highStockSlowMover->stock_qty} units) with very few sales in the last 30 days",
                'suggestion' => 'Recommend a 15% discount promotion to move stock',
                'product_id' => $highStockSlowMover->id,
            ];
        }

        // Sales dropped at least 40% vs the prior 30-day window.
        $decliningProduct = Product::where('is_active', true)
            ->whereNotIn('id', $promotedProductIds)
            ->get()
            ->map(function (Product $p) use ($salesLast30, $salesPrev30) {
                $prev = $salesPrev30[$p->id] ?? 0;
                $curr = $salesLast30[$p->id] ?? 0;

                return ['product' => $p, 'prev' => $prev, 'curr' => $curr, 'drop_percent' => $prev > 0 ? (($prev - $curr) / $prev) * 100 : 0];
            })
            ->filter(fn ($row) => $row['prev'] >= 5 && $row['drop_percent'] >= 40)
            ->sortByDesc('drop_percent')
            ->first();

        if ($decliningProduct) {
            $product = $decliningProduct['product'];
            $signals[] = [
                'icon' => 'bi-graph-down-arrow',
                'reason' => "{$product->name} sales decreased by ".round($decliningProduct['drop_percent'])."% compared to the prior 30 days",
                'suggestion' => 'Recommend a Weekend Promotion to re-engage buyers',
                'product_id' => $product->id,
            ];
        }

        // Near-expiry stock — clearance candidate.
        $nearExpiryProduct = Product::where('is_active', true)
            ->whereNotIn('id', $promotedProductIds)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', now())
            ->whereDate('expiry_date', '<=', now()->addDays(config('billing.near_expiry_days', 7)))
            ->orderBy('expiry_date')
            ->first();

        if ($nearExpiryProduct) {
            $signals[] = [
                'icon' => 'bi-hourglass-split',
                'reason' => "{$nearExpiryProduct->name} is nearing its expiry date ({$nearExpiryProduct->expiry_date->format('d M Y')})",
                'suggestion' => 'Recommend a Clearance Sale before the stock expires',
                'product_id' => $nearExpiryProduct->id,
            ];
        }

        // Active promotion with low display-to-sale conversion — a real signal
        // once it's had a meaningful number of rotation turns.
        $underperforming = Promotion::where('status', Promotion::STATUS_ACTIVE)
            ->where('display_count', '>=', 20)
            ->get()
            ->map(function (Promotion $p) use ($start, $end) {
                $unitsSold = SaleItem::query()
                    ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                    ->where('sale_items.product_id', $p->product_id)
                    ->whereBetween('sales.created_at', [$start, $end])
                    ->sum('sale_items.quantity');

                return ['promotion' => $p, 'conversion' => $p->display_count > 0 ? ($unitsSold / $p->display_count) * 100 : 0];
            })
            ->filter(fn ($row) => $row['conversion'] < 1)
            ->sortBy('conversion')
            ->first();

        if ($underperforming) {
            $promotion = $underperforming['promotion'];
            $signals[] = [
                'icon' => 'bi-emoji-neutral',
                'reason' => "\"{$promotion->title}\" has been shown {$promotion->display_count} times with very little resulting interest",
                'suggestion' => 'Recommend pairing it with a Bundle Offer instead of a standalone discount',
                'product_id' => $promotion->product_id,
            ];
        }

        return $signals;
    }
}
