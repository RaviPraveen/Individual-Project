<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsReportResponses;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RevenueController extends Controller
{
    use BuildsReportResponses;

    public function index(): View
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $end = $now->copy()->endOfDay();

        $today = $this->comparedTotals(
            $now->copy()->startOfDay(), $end,
            $now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(),
        );

        // "This week vs last week" compares like-for-like: the elapsed days
        // of the current week (Mon..today) against the same weekday span
        // one week earlier, not the full previous week.
        $weekStart = $now->copy()->startOfWeek();
        $daysElapsedThisWeek = $weekStart->diffInDays($now);
        $prevWeekStart = $weekStart->copy()->subWeek();

        $week = $this->comparedTotals(
            $weekStart, $end,
            $prevWeekStart, $prevWeekStart->copy()->addDays($daysElapsedThisWeek)->endOfDay(),
        );

        // "This month vs last month" likewise compares month-to-date against
        // the same number of days into the previous month.
        $prevMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth();

        $month = $this->comparedTotals(
            $monthStart, $end,
            $prevMonthStart, $prevMonthStart->copy()->addDays($now->day - 1)->endOfDay(),
        );

        $prevYearStart = $now->copy()->subYear()->startOfYear();

        $year = $this->comparedTotals(
            $now->copy()->startOfYear(), $end,
            $prevYearStart, $prevYearStart->copy()->addDays($now->dayOfYear - 1)->endOfDay(),
        );

        return view('admin.revenue.index', [
            'today' => $today,
            'week' => $week,
            'month' => $month,
            'year' => $year,
            'monthlyTrend' => $this->monthlyTrend(),
            'categoryBreakdown' => $this->categoryTotals($monthStart, $end),
            'topProducts' => $this->productTotals($monthStart, $end)->sortByDesc('revenue')->values()->take(5),
        ]);
    }

    public function byPeriod(Request $request): View|StreamedResponse
    {
        [$start, $end] = $this->dateRange($request);
        $groupBy = $request->input('group_by') === 'week' ? 'week' : 'day';

        $periodExpr = $groupBy === 'week'
            ? "DATE_FORMAT(sales.created_at, '%x-W%v')"
            : 'DATE(sales.created_at)';

        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.created_at', [$start, $end])
            ->selectRaw("{$periodExpr} as period, SUM(sale_items.line_total) as revenue, SUM(sale_items.quantity * COALESCE(sale_items.cost_price, products.cost_price)) as cost")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(function ($row) {
                $margin = $this->withProfitMargin($row);
                $row->revenue = $margin['revenue'];
                $row->cost = $margin['cost'];
                $row->profit = $margin['profit'];
                $row->margin_percent = $margin['margin_percent'];

                return $row;
            });

        $totals = $this->withProfitMargin((object) [
            'revenue' => $rows->sum('revenue'),
            'cost' => $rows->sum('cost'),
        ]);

        if ($request->query('export') === 'csv') {
            return $this->csv('revenue-by-period.csv', ['Period', 'Revenue', 'Cost', 'Profit', 'Margin %'], $rows->map(fn ($r) => [
                $r->period, $r->revenue, $r->cost, $r->profit, $r->margin_percent,
            ]));
        }

        return view('admin.revenue.by-period', [
            'rows' => $rows,
            'totals' => $totals,
            'groupBy' => $groupBy,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ]);
    }

    public function byProduct(Request $request): View|StreamedResponse
    {
        [$start, $end] = $this->dateRangeOrDefault($request, now()->startOfMonth(), now()->endOfDay());
        $categoryId = $request->input('category_id');
        $sort = in_array($request->input('sort'), ['revenue', 'profit', 'margin']) ? $request->input('sort') : 'revenue';

        $rows = $this->productTotals($start, $end, $categoryId);

        $rows = match ($sort) {
            'profit' => $rows->sortByDesc('profit')->values(),
            'margin' => $rows->sortByDesc('margin_percent')->values(),
            default => $rows->sortByDesc('revenue')->values(),
        };

        if ($request->query('export') === 'csv') {
            return $this->csv('revenue-by-product.csv', ['Product', 'Units Sold', 'Revenue', 'Cost', 'Profit', 'Margin %'], $rows->map(fn ($r) => [
                $r->name, $r->qty_sold, $r->revenue, $r->cost, $r->profit, $r->margin_percent,
            ]));
        }

        return view('admin.revenue.by-product', [
            'rows' => $rows,
            'categories' => Category::orderBy('name')->get(),
            'categoryId' => $categoryId,
            'sort' => $sort,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'lowMarginThreshold' => (float) Setting::get('low_margin_threshold_percent', config('billing.low_margin_threshold_percent', 10)),
        ]);
    }

    public function byCategory(Request $request): View|StreamedResponse
    {
        [$start, $end] = $this->dateRangeOrDefault($request, now()->startOfMonth(), now()->endOfDay());
        $sort = in_array($request->input('sort'), ['revenue', 'profit', 'margin']) ? $request->input('sort') : 'revenue';

        $rows = $this->categoryTotals($start, $end);

        $rows = match ($sort) {
            'profit' => $rows->sortByDesc('profit')->values(),
            'margin' => $rows->sortByDesc('margin_percent')->values(),
            default => $rows->sortByDesc('revenue')->values(),
        };

        if ($request->query('export') === 'csv') {
            return $this->csv('revenue-by-category.csv', ['Category', 'Units Sold', 'Revenue', 'Cost', 'Profit', 'Margin %'], $rows->map(fn ($r) => [
                $r->name, $r->qty_sold, $r->revenue, $r->cost, $r->profit, $r->margin_percent,
            ]));
        }

        return view('admin.revenue.by-category', [
            'rows' => $rows,
            'sort' => $sort,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'lowMarginThreshold' => (float) Setting::get('low_margin_threshold_percent', config('billing.low_margin_threshold_percent', 10)),
        ]);
    }

    /**
     * Same as the shared dateRange() but defaults to a caller-supplied range
     * (current month, for the by-Product/by-Category pages) instead of the
     * trait's fixed last-30-days default.
     */
    private function dateRangeOrDefault(Request $request, Carbon $defaultStart, Carbon $defaultEnd): array
    {
        $start = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : $defaultStart;

        $end = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : $defaultEnd;

        return [$start, $end];
    }

    /**
     * Revenue/cost/profit/margin for a date range, plus the percentage
     * change and up/down trend versus a comparison (previous-equivalent)
     * range. Revenue here is product revenue (SUM of sale_items.line_total)
     * rather than Sale::total, so that revenue - cost = profit holds exactly
     * — Sale::total includes tax and bag fee, neither of which has a
     * per-product cost to net against.
     */
    private function comparedTotals(Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd): array
    {
        $current = $this->rangeTotals($start, $end);
        $previous = $this->rangeTotals($prevStart, $prevEnd);

        $current['change_percent'] = $previous['revenue'] > 0
            ? round((($current['revenue'] - $previous['revenue']) / $previous['revenue']) * 100, 1)
            : ($current['revenue'] > 0 ? 100.0 : 0.0);
        $current['trend'] = $current['change_percent'] >= 0 ? 'up' : 'down';

        return $current;
    }

    private function rangeTotals(Carbon $start, Carbon $end): array
    {
        $row = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(sale_items.line_total), 0) as revenue, COALESCE(SUM(sale_items.quantity * COALESCE(sale_items.cost_price, products.cost_price)), 0) as cost')
            ->first();

        return $this->withProfitMargin($row);
    }

    private function withProfitMargin(object $row): array
    {
        $revenue = (float) $row->revenue;
        $cost = (float) $row->cost;
        $profit = $revenue - $cost;
        $margin = $revenue > 0 ? round($profit / $revenue * 100, 1) : 0.0;

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $profit,
            'margin_percent' => $margin,
        ];
    }

    /**
     * Grouped by DATE() (portable across MySQL and the SQLite test driver)
     * rather than DATE_FORMAT('%Y-%m'), which SQLite doesn't support — then
     * rolled up into months in PHP.
     */
    private function monthlyTrend(): array
    {
        $start = now()->subMonths(11)->startOfMonth();

        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.created_at', '>=', $start)
            ->selectRaw('DATE(sales.created_at) as sale_date, SUM(sale_items.line_total) as revenue')
            ->groupBy('sale_date')
            ->pluck('revenue', 'sale_date');

        $byMonth = [];
        foreach ($rows as $date => $revenue) {
            $key = Carbon::parse($date)->format('Y-m');
            $byMonth[$key] = ($byMonth[$key] ?? 0) + (float) $revenue;
        }

        $labels = [];
        $data = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $key = $month->format('Y-m');
            $labels[] = $month->format('M Y');
            $data[] = $byMonth[$key] ?? 0.0;
        }

        return compact('labels', 'data');
    }

    private function categoryTotals(Carbon $start, Carbon $end)
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('sales.created_at', [$start, $end])
            ->selectRaw("COALESCE(categories.name, 'Uncategorized') as name, SUM(sale_items.quantity) as qty_sold, SUM(sale_items.line_total) as revenue, SUM(sale_items.quantity * COALESCE(sale_items.cost_price, products.cost_price)) as cost")
            ->groupByRaw("COALESCE(categories.name, 'Uncategorized')")
            ->get()
            ->map(function ($row) {
                $margin = $this->withProfitMargin($row);
                $row->cost = $margin['cost'];
                $row->profit = $margin['profit'];
                $row->margin_percent = $margin['margin_percent'];

                return $row;
            });
    }

    private function productTotals(Carbon $start, Carbon $end, $categoryId = null)
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.created_at', [$start, $end])
            ->when($categoryId, fn ($q) => $q->where('products.category_id', $categoryId))
            ->selectRaw('products.id as product_id, products.name as name, SUM(sale_items.quantity) as qty_sold, SUM(sale_items.line_total) as revenue, SUM(sale_items.quantity * COALESCE(sale_items.cost_price, products.cost_price)) as cost')
            ->groupBy('products.id', 'products.name')
            ->get()
            ->map(function ($row) {
                $margin = $this->withProfitMargin($row);
                $row->cost = $margin['cost'];
                $row->profit = $margin['profit'];
                $row->margin_percent = $margin['margin_percent'];

                return $row;
            });
    }
}
