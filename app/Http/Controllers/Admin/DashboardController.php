<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiLog;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Services\AiService;
use App\Services\PromotionAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private AiService $gemini) {}

    public function index(Request $request, PromotionAnalytics $promotionAnalytics): View
    {
        $todaySales = Sale::whereDate('created_at', today());
        $monthSales = Sale::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);

        $stats = [
            'products' => Product::count(),
            'low_stock' => Product::whereColumn('stock_qty', '<=', 'reorder_level')->count(),
            'suppliers' => Supplier::count(),
            'customers' => Customer::count(),
            'today_total' => (float) (clone $todaySales)->sum('total'),
            'today_count' => (clone $todaySales)->count(),
            'month_total' => (float) (clone $monthSales)->sum('total'),
            'month_count' => (clone $monthSales)->count(),
            'refunds_month_total' => (float) SaleReturn::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total_refunded'),
        ];

        $topCategory = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('sales.created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw("COALESCE(categories.name, 'Uncategorized') as name, SUM(sale_items.quantity) as qty")
            ->groupByRaw("COALESCE(categories.name, 'Uncategorized')")
            ->orderByDesc('qty')
            ->first();

        return view('admin.dashboard', [
            'stats' => $stats,
            'topCategory' => $topCategory,
            'summary' => $this->businessSummary($request, $stats, $topCategory),
            'geminiConfigured' => $this->gemini->isConfigured(),
            'salesTrend' => $this->salesTrend(),
            'categoryBreakdown' => $this->categoryBreakdown(),
            'promotions' => $promotionAnalytics->dashboardSummary(),
        ]);
    }

    private function salesTrend(): array
    {
        $byDay = Sale::query()
            ->selectRaw('DATE(created_at) as day, SUM(total) as total')
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $data = [];

        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('M j');
            $data[] = (float) ($byDay[$date->format('Y-m-d')] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    private function categoryBreakdown(): array
    {
        $rows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('sales.created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw("COALESCE(categories.name, 'Uncategorized') as name, SUM(sale_items.line_total) as revenue")
            ->groupByRaw("COALESCE(categories.name, 'Uncategorized')")
            ->orderByDesc('revenue')
            ->limit(6)
            ->get();

        return [
            'labels' => $rows->pluck('name')->all(),
            'data' => $rows->pluck('revenue')->map(fn ($v) => (float) $v)->all(),
        ];
    }

    /**
     * Cached per half-hour so a busy dashboard doesn't re-call the AI service
     * (or write a fresh ai_logs row) on every single page load.
     *
     * Cache::remember() can't tell "cached null" apart from "cache miss" — it
     * re-runs the callback forever if the value is null, which it always is
     * while the AI service is unconfigured. Cache an empty string as the "no
     * narrative" sentinel instead, and translate it back to null for the view.
     */
    private function businessSummary(Request $request, array $stats, $topCategory): ?string
    {
        $cacheKey = 'admin_business_summary_'.now()->format('Y_m_d_H').'_'.intdiv(now()->minute, 30);

        $cached = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($request, $stats, $topCategory) {
            $prompt = "You are a retail business analyst for a Sri Lankan supermarket called Welcome Foodcity. ".
                "Based ONLY on the following computed data, write a short (2-3 sentence) plain-language business ".
                "performance summary for the store owner. Do not invent any numbers beyond what is given.\n\n".
                "Today's sales: {$stats['today_count']} transactions, revenue {$stats['today_total']}\n".
                "This month's sales so far: {$stats['month_count']} transactions, revenue {$stats['month_total']}\n".
                "Total active products: {$stats['products']}\n".
                "Products at/below reorder level: {$stats['low_stock']}\n".
                'Top category this month: '.($topCategory->name ?? 'no sales yet')."\n".
                "Total suppliers: {$stats['suppliers']}, total customers: {$stats['customers']}\n".
                "Refunds this month: {$stats['refunds_month_total']}";

            $narrative = $this->gemini->generate($prompt);

            AiLog::create([
                'user_id' => $request->user()->id,
                'query' => 'Admin dashboard business summary ('.now()->format('Y-m-d H:i').')',
                'response' => $narrative ?? '[AI unavailable — narrative not generated]',
            ]);

            return $narrative ?? '';
        });

        return $cached === '' ? null : $cached;
    }
}
