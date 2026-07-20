<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private GeminiService $gemini) {}

    public function index(): View
    {
        return view('admin.reports.index');
    }

    public function sales(Request $request): View|StreamedResponse
    {
        [$start, $end] = $this->dateRange($request);
        $groupBy = in_array($request->input('group_by'), ['day', 'week', 'month']) ? $request->input('group_by') : 'day';

        $periodExpr = match ($groupBy) {
            'week' => "DATE_FORMAT(created_at, '%x-W%v')",
            'month' => "DATE_FORMAT(created_at, '%Y-%m')",
            default => 'DATE(created_at)',
        };

        $rows = Sale::whereBetween('created_at', [$start, $end])
            ->selectRaw("{$periodExpr} as period, COUNT(*) as transactions, SUM(subtotal) as subtotal, SUM(discount) as discount, SUM(tax) as tax, SUM(total) as total")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        if ($request->query('export') === 'csv') {
            return $this->csv('sales-summary.csv', ['Period', 'Transactions', 'Subtotal', 'Discount', 'Tax', 'Total'], $rows->map(fn ($r) => [
                $r->period, $r->transactions, $r->subtotal, $r->discount, $r->tax, $r->total,
            ]));
        }

        return view('admin.reports.sales', [
            'rows' => $rows,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'groupBy' => $groupBy,
        ]);
    }

    public function byProduct(Request $request): View|StreamedResponse
    {
        [$start, $end] = $this->dateRange($request);

        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.created_at', [$start, $end])
            ->selectRaw('products.name as name, SUM(sale_items.quantity) as qty_sold, SUM(sale_items.line_total) as revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->get();

        if ($request->query('export') === 'csv') {
            return $this->csv('sales-by-product.csv', ['Product', 'Quantity Sold', 'Revenue'], $rows->map(fn ($r) => [
                $r->name, $r->qty_sold, $r->revenue,
            ]));
        }

        return view('admin.reports.by-product', [
            'rows' => $rows,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ]);
    }

    public function byCategory(Request $request): View|StreamedResponse
    {
        [$start, $end] = $this->dateRange($request);

        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('sales.created_at', [$start, $end])
            ->selectRaw("COALESCE(categories.name, 'Uncategorized') as name, SUM(sale_items.quantity) as qty_sold, SUM(sale_items.line_total) as revenue")
            ->groupBy('name')
            ->orderByDesc('revenue')
            ->get();

        if ($request->query('export') === 'csv') {
            return $this->csv('sales-by-category.csv', ['Category', 'Quantity Sold', 'Revenue'], $rows->map(fn ($r) => [
                $r->name, $r->qty_sold, $r->revenue,
            ]));
        }

        return view('admin.reports.by-category', [
            'rows' => $rows,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ]);
    }

    public function byCashier(Request $request): View|StreamedResponse
    {
        [$start, $end] = $this->dateRange($request);

        $rows = Sale::query()
            ->join('users', 'users.id', '=', 'sales.cashier_id')
            ->whereBetween('sales.created_at', [$start, $end])
            ->selectRaw('users.name as name, COUNT(sales.id) as transactions, SUM(sales.total) as revenue')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('revenue')
            ->get();

        if ($request->query('export') === 'csv') {
            return $this->csv('sales-by-cashier.csv', ['Cashier', 'Transactions', 'Revenue'], $rows->map(fn ($r) => [
                $r->name, $r->transactions, $r->revenue,
            ]));
        }

        return view('admin.reports.by-cashier', [
            'rows' => $rows,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ]);
    }

    public function lowStock(Request $request): View|StreamedResponse
    {
        $rows = Product::query()
            ->with('category')
            ->whereColumn('stock_qty', '<=', 'reorder_level')
            ->where('is_active', true)
            ->orderBy('stock_qty')
            ->get();

        if ($request->query('export') === 'csv') {
            return $this->csv('low-stock.csv', ['Product', 'SKU', 'Category', 'Stock', 'Reorder Level'], $rows->map(fn ($r) => [
                $r->name, $r->sku, $r->category?->name, $r->stock_qty, $r->reorder_level,
            ]));
        }

        return view('admin.reports.low-stock', compact('rows'));
    }

    public function profit(Request $request): View|StreamedResponse
    {
        [$start, $end] = $this->dateRange($request);

        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.created_at', [$start, $end])
            ->selectRaw('products.name as name, SUM(sale_items.quantity) as qty_sold, SUM(sale_items.line_total) as revenue, SUM(sale_items.quantity * products.cost_price) as total_cost')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(function ($row) {
                $row->profit = $row->revenue - $row->total_cost;
                $row->margin_percent = $row->revenue > 0 ? round($row->profit / $row->revenue * 100, 1) : 0;

                return $row;
            });

        if ($request->query('export') === 'csv') {
            return $this->csv('profit-margin.csv', ['Product', 'Quantity Sold', 'Revenue', 'Cost', 'Profit', 'Margin %'], $rows->map(fn ($r) => [
                $r->name, $r->qty_sold, $r->revenue, $r->total_cost, $r->profit, $r->margin_percent,
            ]));
        }

        return view('admin.reports.profit', [
            'rows' => $rows,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'narrative' => $this->profitNarrative($request, $rows, $start, $end),
        ]);
    }

    /**
     * Cached per date-range+row-count so repeated visits to the same range
     * don't re-call Gemini. See DashboardController::businessSummary() for
     * why a null narrative is cached as '' rather than a literal null.
     */
    private function profitNarrative(Request $request, $rows, Carbon $start, Carbon $end): ?string
    {
        if ($rows->isEmpty()) {
            return null;
        }

        $cacheKey = 'profit_narrative_'.$start->format('Ymd').'_'.$end->format('Ymd').'_'.$rows->count().'_'.intdiv(now()->minute, 30).'_'.now()->format('YmdH');

        $cached = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($request, $rows, $start, $end) {
            $top = $rows->sortByDesc('margin_percent')->take(3);
            $bottom = $rows->sortBy('margin_percent')->take(3);

            $topList = $top->map(fn ($r) => "{$r->name} ({$r->margin_percent}% margin, profit {$r->profit})")->implode(', ');
            $bottomList = $bottom->map(fn ($r) => "{$r->name} ({$r->margin_percent}% margin, profit {$r->profit})")->implode(', ');

            $prompt = "You are a retail profitability analyst for a Sri Lankan supermarket called Welcome Foodcity. ".
                "Based ONLY on the following computed data for the period {$start->format('Y-m-d')} to {$end->format('Y-m-d')}, ".
                "write a short (2-3 sentence) plain-language explanation of which products are most and least profitable ".
                "and why that matters for the business. Do not invent any numbers beyond what is given.\n\n".
                'Total revenue: '.$rows->sum('revenue')."\n".
                'Total profit: '.$rows->sum('profit')."\n".
                "Most profitable products: {$topList}\n".
                "Least profitable products: {$bottomList}";

            $narrative = $this->gemini->generate($prompt);

            AiLog::create([
                'user_id' => $request->user()->id,
                'query' => "Profit analysis narrative ({$start->format('Y-m-d')} to {$end->format('Y-m-d')})",
                'response' => $narrative ?? '[AI unavailable — narrative not generated]',
            ]);

            return $narrative ?? '';
        });

        return $cached === '' ? null : $cached;
    }

    private function dateRange(Request $request): array
    {
        $start = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->subDays(30)->startOfDay();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfDay();

        return [$start, $end];
    }

    private function csv(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $header);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
