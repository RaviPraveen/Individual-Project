<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsReportResponses;
use App\Http\Controllers\Controller;
use App\Models\AiLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Services\AiService;
use App\Services\ProductSupplierResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use BuildsReportResponses;

    public function __construct(
        private AiService $aiService,
        private ProductSupplierResolver $supplierResolver,
    ) {}

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
            ->groupByRaw("COALESCE(categories.name, 'Uncategorized')")
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

    public function nearExpiry(Request $request): View|StreamedResponse
    {
        $days = (int) $request->input('days', config('billing.near_expiry_days', 7));
        $days = max(1, $days);

        $rows = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', now())
            ->whereDate('expiry_date', '<=', now()->addDays($days))
            ->orderBy('expiry_date')
            ->get();

        if ($request->query('export') === 'csv') {
            return $this->csv('near-expiry.csv', ['Product', 'SKU', 'Category', 'Stock', 'Expiry Date', 'Days Left'], $rows->map(fn ($r) => [
                $r->name, $r->sku, $r->category?->name, $r->stock_qty, $r->expiry_date->format('Y-m-d'), (int) now()->startOfDay()->diffInDays($r->expiry_date, false),
            ]));
        }

        return view('admin.reports.near-expiry', [
            'rows' => $rows,
            'days' => $days,
        ]);
    }

    public function deadStock(Request $request): View|StreamedResponse
    {
        $days = max(1, (int) $request->input('days', config('billing.dead_stock_days', 30)));
        $maxQtySold = max(0, (int) $request->input('max_qty_sold', config('billing.dead_stock_max_qty_sold', 3)));
        $categoryId = $request->input('category_id');
        $supplierId = $request->input('supplier_id');
        $sort = $request->input('sort', 'slowest');

        $start = now()->subDays($days)->startOfDay();
        $end = now()->endOfDay();
        $nearExpiryDays = config('billing.near_expiry_days', 7);

        $products = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->get();

        $salesMap = $this->slowMovingSalesMap($start, $end);
        $supplierMap = $this->supplierResolver->resolve($products->pluck('id')->all());

        $rows = $products
            ->map(function (Product $product) use ($salesMap, $supplierMap, $days, $nearExpiryDays) {
                $qtySold = $salesMap[$product->id] ?? 0;
                $supplier = $supplierMap[$product->id] ?? null;

                return [
                    'product' => $product,
                    'qty_sold' => $qtySold,
                    'velocity_per_week' => round($qtySold / ($days / 7), 2),
                    'stock_value' => round($product->stock_qty * $product->cost_price, 2),
                    'supplier' => $supplier,
                    'days_to_expiry' => $product->expiry_date ? (int) now()->startOfDay()->diffInDays($product->expiry_date, false) : null,
                    'is_near_expiry' => $product->expiry_date !== null && $product->isNearExpiry($nearExpiryDays),
                ];
            })
            ->filter(fn ($row) => $row['qty_sold'] <= $maxQtySold)
            ->when($supplierId, fn ($rows) => $rows->filter(fn ($row) => ($row['supplier']['id'] ?? null) == $supplierId))
            ->values();

        $rows = $sort === 'value'
            ? $rows->sortByDesc('stock_value')->values()
            : $rows->sortBy([['qty_sold', 'asc'], ['velocity_per_week', 'asc']])->values();

        if ($request->query('export') === 'csv') {
            return $this->csv('dead-stock.csv', ['Product', 'SKU', 'Category', 'Stock Qty', 'Qty Sold', 'Velocity/Week', 'Stock Value', 'Supplier', 'Days to Expiry'], $rows->map(fn ($row) => [
                $row['product']->name,
                $row['product']->sku,
                $row['product']->category?->name,
                $row['product']->stock_qty,
                $row['qty_sold'],
                $row['velocity_per_week'],
                $row['stock_value'],
                $row['supplier']['name'] ?? '',
                $row['days_to_expiry'],
            ]));
        }

        return view('admin.reports.dead-stock', [
            'rows' => $rows,
            'days' => $days,
            'maxQtySold' => $maxQtySold,
            'categoryId' => $categoryId,
            'supplierId' => $supplierId,
            'sort' => $sort,
            'categories' => Category::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'narrative' => $this->deadStockNarrative($request, $rows),
        ]);
    }

    private function slowMovingSalesMap(Carbon $start, Carbon $end): array
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.created_at', [$start, $end])
            ->selectRaw('sale_items.product_id, SUM(sale_items.quantity) as qty_sold')
            ->groupBy('sale_items.product_id')
            ->pluck('qty_sold', 'product_id')
            ->all();
    }

    // Cache the recommendation for 30 minutes to limit API requests.
    private function deadStockNarrative(Request $request, $rows): ?string
    {
        if ($rows->isEmpty()) {
            return null;
        }

        $days = max(1, (int) $request->input('days', config('billing.dead_stock_days', 30)));
        $list = $rows->sortByDesc('stock_value')->take(5)->map(function ($row) use ($days) {
            $supplierName = $row['supplier']['name'] ?? 'unknown supplier';
            $expiry = $row['is_near_expiry'] ? ', nearing expiry' : '';

            return "{$row['product']->name}: sold {$row['qty_sold']} units in {$days} days, stock value Rs {$row['stock_value']}, usual supplier {$supplierName}{$expiry}";
        })->implode("\n");
        $cacheKey = 'dead_stock_narrative_cf_'.md5($list);

        $cached = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($request, $list) {

            $prompt = 'You are a retail inventory analyst for a Sri Lankan supermarket called Welcome Foodcity. '.
                'Based ONLY on the following computed slow-moving/dead-stock data, write a short (3-4 sentence) '.
                'recommendation on which products are the strongest candidates to return to their supplier and why. '.
                'Do not invent numbers, losses, savings or supplier return terms. '.
                'Mention nearing expiry only for products explicitly marked that way. '.
                "Treat returns as something to review with the supplier, not a guaranteed option.\n\n{$list}";

            $narrative = $this->aiService->generate($prompt);

            AiLog::create([
                'user_id' => $request->user()->id,
                'query' => 'Dead-stock report recommendation ('.now()->format('Y-m-d H:i').')',
                'response' => $narrative ?? '[AI unavailable — narrative not generated]',
            ]);

            return $narrative ?? '';
        });

        return $cached === '' ? null : $cached;
    }

    public function profit(Request $request): View|StreamedResponse
    {
        [$start, $end] = $this->dateRange($request);

        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.created_at', [$start, $end])
            ->selectRaw('products.name as name, SUM(sale_items.quantity) as qty_sold, SUM(sale_items.line_total) as revenue, SUM(sale_items.quantity * COALESCE(sale_items.cost_price, products.cost_price)) as total_cost')
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

    // Cache by date range and row count. An empty string also caches a failed request.
    private function profitNarrative(Request $request, $rows, Carbon $start, Carbon $end): ?string
    {
        if ($rows->isEmpty()) {
            return null;
        }

        $cacheKey = 'profit_narrative_cf_'.$start->format('Ymd').'_'.$end->format('Ymd').'_'.$rows->count().'_'.intdiv(now()->minute, 30).'_'.now()->format('YmdH');

        $cached = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($request, $rows, $start, $end) {
            $ranked = $rows->sortByDesc('margin_percent')->values();
            $top = $ranked->take(3);
            $bottom = $ranked->slice($top->count())->sortBy('margin_percent')->take(3);
            $highestMargin = $ranked->first();
            $highestProfit = $rows->sortByDesc('profit')->first();

            $topList = $top->map(fn ($r) => "{$r->name} ({$r->margin_percent}% margin, profit {$r->profit})")->implode(', ');
            $bottomList = $bottom->map(fn ($r) => "{$r->name} ({$r->margin_percent}% margin, profit {$r->profit})")->implode(', ');

            $prompt = "You are a retail profitability analyst for a Sri Lankan supermarket called Welcome Foodcity. ".
                "Based ONLY on the following computed data for the period {$start->format('Y-m-d')} to {$end->format('Y-m-d')}, ".
                "write a short (2-3 sentence) plain-language comparison of the listed product margins and profit amounts. ".
                "Distinguish percentage margin from total profit. Do not invent any numbers beyond what is given.\n\n".
                'Describe the supplied margins and profits only. Do not infer demand, popularity or sales trends. '.
                "If no lower-margin comparison products are listed, do not invent any.\n\n".
                "Computed comparison: {$highestMargin->name} has the highest percentage margin ({$highestMargin->margin_percent}%). ".
                "{$highestProfit->name} has the highest total profit (Rs {$highestProfit->profit}). Use these rankings exactly.\n".
                'Total revenue: '.$rows->sum('revenue')."\n".
                'Total profit: '.$rows->sum('profit')."\n".
                "Highest-margin products: {$topList}\n".
                'Other lower-margin products: '.($bottomList ?: 'None in this report');

            $narrative = $this->aiService->generate($prompt);

            AiLog::create([
                'user_id' => $request->user()->id,
                'query' => "Profit analysis narrative ({$start->format('Y-m-d')} to {$end->format('Y-m-d')})",
                'response' => $narrative ?? '[AI unavailable — narrative not generated]',
            ]);

            return $narrative ?? '';
        });

        return $cached === '' ? null : $cached;
    }

}
