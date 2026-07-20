<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ForecastService
{
    /**
     * Deterministic weekly-average forecast for every active product.
     * The number here is never produced by AI — it is a plain historical
     * average, projected forward. AI is only used later to narrate it.
     */
    public function forecastAll(int $lookbackWeeks = 8): Collection
    {
        $since = now()->subWeeks($lookbackWeeks);

        $soldByProduct = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.created_at', '>=', $since)
            ->selectRaw('sale_items.product_id, SUM(sale_items.quantity) as total_qty')
            ->groupBy('sale_items.product_id')
            ->pluck('total_qty', 'product_id');

        return Product::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => $this->buildForecast(
                $product,
                (int) ($soldByProduct[$product->id] ?? 0),
                $lookbackWeeks
            ));
    }

    public function forecastForProduct(Product $product, int $lookbackWeeks = 8): array
    {
        $since = now()->subWeeks($lookbackWeeks);

        $totalQtySold = (int) DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.created_at', '>=', $since)
            ->where('sale_items.product_id', $product->id)
            ->sum('sale_items.quantity');

        return $this->buildForecast($product, $totalQtySold, $lookbackWeeks);
    }

    private function buildForecast(Product $product, int $totalQtySold, int $lookbackWeeks): array
    {
        $avgWeeklyQty = $lookbackWeeks > 0 ? $totalQtySold / $lookbackWeeks : 0;
        $forecast7d = round($avgWeeklyQty, 1);
        $forecast30d = round($avgWeeklyQty * (30 / 7), 1);
        $projectedStock30d = round($product->stock_qty - $forecast30d, 1);
        $recommendedReorderQty = max(0, (int) ceil($forecast30d - $product->stock_qty + $product->reorder_level));

        return [
            'product' => $product,
            'lookback_weeks' => $lookbackWeeks,
            'total_qty_sold' => $totalQtySold,
            'avg_weekly_qty' => round($avgWeeklyQty, 1),
            'forecast_7d' => $forecast7d,
            'forecast_30d' => $forecast30d,
            'projected_stock_30d' => $projectedStock30d,
            'recommended_reorder_qty' => $recommendedReorderQty,
            'needs_reorder' => $projectedStock30d < $product->reorder_level,
        ];
    }
}
