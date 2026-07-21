<?php

namespace App\Services;

use App\Models\Promotion;
use App\Models\SaleItem;
use Illuminate\Support\Collection;

/**
 * There's no per-customer tracking on the Customer Display (it's a shared
 * screen, not a login), so "Views" and "Estimated Reach" are both derived
 * from the same display_count counter (incremented once per rotation turn
 * — see CustomerDisplayController::markPromotionViewed()) rather than
 * being independently measured signals. Conversion Rate is a rough
 * display-to-sale proxy for the same reason: a display doesn't map to a
 * specific customer visit, so treat it as directional, not exact.
 */
class PromotionAnalytics
{
    public function metricsFor(Promotion $promotion): array
    {
        $end = $promotion->end_date->isFuture() ? now() : $promotion->end_date;

        $sales = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.product_id', $promotion->product_id)
            ->whereBetween('sales.created_at', [$promotion->start_date, $end])
            ->selectRaw('COALESCE(SUM(sale_items.quantity), 0) as qty_sold, COALESCE(SUM(sale_items.line_total), 0) as revenue')
            ->first();

        $unitsSold = (int) $sales->qty_sold;
        $revenue = (float) $sales->revenue;

        return [
            'views' => $promotion->display_count,
            'display_count' => $promotion->display_count,
            'estimated_reach' => $promotion->display_count,
            'units_sold' => $unitsSold,
            'revenue' => $revenue,
            'conversion_rate' => $promotion->display_count > 0 ? round(($unitsSold / $promotion->display_count) * 100, 1) : 0.0,
        ];
    }

    /**
     * @return Collection<int, array{promotion: Promotion, metrics: array}>
     */
    public function allWithMetrics(): Collection
    {
        return Promotion::with('product')->get()
            ->map(fn (Promotion $p) => ['promotion' => $p, 'metrics' => $this->metricsFor($p)]);
    }

    public function bestPerformers(int $limit = 5): Collection
    {
        return $this->allWithMetrics()
            ->sortByDesc(fn ($row) => $row['metrics']['revenue'])
            ->take($limit)
            ->values();
    }

    public function dashboardSummary(): array
    {
        $best = $this->bestPerformers(1)->first();

        return [
            'active' => Promotion::where('status', Promotion::STATUS_ACTIVE)->count(),
            'scheduled' => Promotion::where('status', Promotion::STATUS_SCHEDULED)->count(),
            'revenue_this_month' => (float) $this->allWithMetrics()->sum(function ($row) {
                $promotion = $row['promotion'];

                return $promotion->start_date->isSameMonth(now()) || $promotion->end_date->isSameMonth(now())
                    ? $row['metrics']['revenue']
                    : 0;
            }),
            'best_promotion' => $best && $best['metrics']['revenue'] > 0 ? $best['promotion'] : null,
        ];
    }
}
