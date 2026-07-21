<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiLog;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Services\AiService;
use App\Services\ForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AiChatController extends Controller
{
    public function __construct(
        private AiService $gemini,
        private ForecastService $forecastService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->role === 'admin';

        $conversations = AiConversation::where('user_id', $user->id)->orderByDesc('updated_at')->get();

        $activeId = $request->integer('conversation') ?: null;
        $active = $activeId
            ? $conversations->firstWhere('id', $activeId)
            : $conversations->first();

        $messages = $active ? $active->logs()->get() : collect();

        return view($isAdmin ? 'admin.ai-chat.index' : 'cashier.ai-chat.index', [
            'conversationGroups' => $this->groupConversations($conversations),
            'activeConversation' => $active,
            'messages' => $messages,
            'geminiConfigured' => $this->gemini->isConfigured(),
            'suggestions' => $this->suggestionChips($isAdmin),
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $isAdmin = $user->role === 'admin';

        $conversation = $this->resolveConversation($user, $validated['conversation_id'] ?? null, $validated['message']);

        $context = $isAdmin ? $this->buildAdminContext() : $this->buildCashierContext();

        $prompt = "You are an internal staff assistant for Welcome Foodcity, a supermarket in Batticaloa, ".
            "Sri Lanka. Answer ONLY using the data provided below. Do not invent prices, stock levels, or ".
            "figures that are not present in this data. Keep answers concise. You may use markdown ".
            "(headings, bold, lists, tables) where it helps readability. If the question cannot be answered ".
            "from this data, say so plainly.\n\nDATA:\n{$context}\n\nSTAFF QUESTION: {$validated['message']}";

        $answer = $this->gemini->generate($prompt);
        $widget = $this->buildWidget($validated['message'], $isAdmin);

        $log = AiLog::create([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'query' => $validated['message'],
            'response' => $answer ?? 'The AI assistant is currently unavailable. Please try again later.',
            'widget' => $widget,
        ]);

        $conversation->touch();

        return response()->json([
            'conversation_id' => $conversation->id,
            'conversation_title' => $conversation->title,
            'message' => [
                'id' => $log->id,
                'query' => $log->query,
                'response' => $log->response,
                'feedback' => $log->feedback,
                'widget' => $log->widget,
                'created_at' => $log->created_at->format('H:i'),
            ],
        ]);
    }

    public function switchConversation(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $messages = $conversation->logs()->get()->map(fn ($log) => [
            'id' => $log->id,
            'query' => $log->query,
            'response' => $log->response,
            'feedback' => $log->feedback,
            'widget' => $log->widget,
            'created_at' => $log->created_at->format('H:i'),
        ]);

        return response()->json([
            'conversation_id' => $conversation->id,
            'conversation_title' => $conversation->title,
            'messages' => $messages,
        ]);
    }

    public function renameConversation(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
        ]);

        $conversation->update(['title' => $validated['title']]);

        return response()->json(['title' => $conversation->title]);
    }

    public function deleteConversation(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->delete();

        return response()->json(['ok' => true]);
    }

    public function clearConversation(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->logs()->delete();
        $conversation->update(['title' => null]);

        return response()->json(['ok' => true]);
    }

    public function feedback(Request $request, AiLog $log): JsonResponse
    {
        if ($log->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'feedback' => ['nullable', 'in:like,dislike'],
        ]);

        $log->update(['feedback' => $validated['feedback'] ?? null]);

        return response()->json(['feedback' => $log->feedback]);
    }

    private function authorizeConversation(Request $request, AiConversation $conversation): void
    {
        if ($conversation->user_id !== $request->user()->id) {
            abort(403);
        }
    }

    private function resolveConversation($user, ?int $conversationId, string $firstMessage): AiConversation
    {
        if ($conversationId) {
            $conversation = AiConversation::where('id', $conversationId)->where('user_id', $user->id)->first();

            if ($conversation) {
                return $conversation;
            }
        }

        return AiConversation::create([
            'user_id' => $user->id,
            'title' => mb_strlen($firstMessage) > 60 ? mb_substr($firstMessage, 0, 57).'...' : $firstMessage,
        ]);
    }

    private function groupConversations($conversations): array
    {
        $groups = ['Today' => [], 'Yesterday' => [], 'Last 7 Days' => [], 'Older' => []];

        foreach ($conversations as $conversation) {
            $date = $conversation->updated_at;
            $key = match (true) {
                $date->isToday() => 'Today',
                $date->isYesterday() => 'Yesterday',
                $date->greaterThanOrEqualTo(now()->subDays(7)) => 'Last 7 Days',
                default => 'Older',
            };
            $groups[$key][] = $conversation;
        }

        return array_filter($groups, fn ($items) => count($items) > 0);
    }

    private function suggestionChips(bool $isAdmin): array
    {
        $shared = [
            ['icon' => '📦', 'text' => 'Which products need restocking?'],
        ];

        if (! $isAdmin) {
            return $shared;
        }

        return array_merge($shared, [
            ['icon' => '📊', 'text' => "Show today's sales"],
            ['icon' => '💰', 'text' => 'What is my revenue this month?'],
            ['icon' => '📉', 'text' => 'Which products are selling slowly?'],
            ['icon' => '🛒', 'text' => 'Show purchase orders pending'],
            ['icon' => '👥', 'text' => 'Top customers'],
        ]);
    }

    /**
     * Detects which (if any) real, deterministically-computed widget to show
     * alongside the AI's narrative answer. Never derived from the AI's own
     * text — the widget data is computed straight from the database, the
     * same "AI narrates real numbers, never invents them" principle used
     * everywhere else in this app.
     */
    private function buildWidget(string $message, bool $isAdmin): ?array
    {
        $text = mb_strtolower($message);

        if ($isAdmin && $this->matchesAny($text, ['top customer', 'best customer', 'loyal customer'])) {
            return $this->customersWidget();
        }

        if ($isAdmin && $this->matchesAny($text, ['purchase order', 'pending order', 'po pending', 'supplier order'])) {
            return $this->purchaseOrdersWidget();
        }

        if ($isAdmin && $this->matchesAny($text, ['trend', 'over time', 'chart', 'graph', 'by category', 'breakdown'])) {
            return $this->chartWidget();
        }

        if ($isAdmin && $this->matchesAny($text, ['revenue', 'sales today', "today's sales", 'how much', 'overview', 'summary', 'this month', 'dashboard'])) {
            return $this->statsWidget($text);
        }

        if ($this->matchesAny($text, ['restock', 'reorder', 'low stock', 'stock', 'inventory', 'selling slow', 'slow mov', 'which products', 'product'])) {
            return $this->productsWidget($text);
        }

        return null;
    }

    private function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function statsWidget(string $text): array
    {
        $scopeToday = str_contains($text, 'today');
        $start = $scopeToday ? now()->startOfDay() : now()->startOfMonth();
        $end = $scopeToday ? now()->endOfDay() : now()->endOfMonth();

        $sales = DB::table('sales')->whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue')->first();

        return [
            'type' => 'stats',
            'items' => [
                ['icon' => 'bi-cash-stack', 'tone' => 'success', 'label' => 'Revenue', 'value' => 'Rs. '.number_format($sales->revenue, 2)],
                ['icon' => 'bi-receipt', 'tone' => 'info', 'label' => 'Orders', 'value' => number_format($sales->orders)],
                ['icon' => 'bi-box-seam', 'tone' => 'primary', 'label' => 'Products', 'value' => number_format(Product::where('is_active', true)->count())],
                ['icon' => 'bi-exclamation-triangle', 'tone' => 'warning', 'label' => 'Low Stock', 'value' => number_format(Product::whereColumn('stock_qty', '<=', 'reorder_level')->where('is_active', true)->count())],
                ['icon' => 'bi-people', 'tone' => 'success', 'label' => 'Customers', 'value' => number_format(Customer::count())],
            ],
        ];
    }

    private function chartWidget(): array
    {
        $rows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('sales.created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw("COALESCE(categories.name, 'Uncategorized') as name, SUM(sale_items.line_total) as revenue")
            ->groupBy('name')
            ->orderByDesc('revenue')
            ->limit(6)
            ->get();

        return [
            'type' => 'chart',
            'chart_type' => 'bar',
            'title' => 'Revenue by Category (This Month)',
            'labels' => $rows->pluck('name')->all(),
            'data' => $rows->pluck('revenue')->map(fn ($v) => (float) $v)->all(),
        ];
    }

    private function productsWidget(string $text): array
    {
        $slow = $this->matchesAny($text, ['slow', 'selling slow']);

        $query = Product::where('is_active', true)->with('category');

        if (! $slow && $this->matchesAny($text, ['restock', 'reorder', 'low stock'])) {
            $query->whereColumn('stock_qty', '<=', 'reorder_level');
        }

        $products = $query->orderBy(
            $slow ? 'stock_qty' : 'name',
            $slow ? 'desc' : 'asc'
        )->limit(10)->get();

        if ($slow) {
            $soldQtyByProduct = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sales.created_at', '>=', now()->subDays(30))
                ->selectRaw('product_id, SUM(quantity) as qty')
                ->groupBy('product_id')
                ->pluck('qty', 'product_id');

            $products = Product::where('is_active', true)->with('category')->get()
                ->sortBy(fn ($p) => (int) ($soldQtyByProduct[$p->id] ?? 0))
                ->take(10);
        }

        $rows = $products->map(fn ($p) => [
            'name' => $p->name,
            'sku' => $p->sku,
            'stock' => $p->stock_qty,
            'reorder_level' => $p->reorder_level,
            'price' => number_format($p->selling_price, 2),
            'status' => $p->stock_qty <= $p->reorder_level ? 'Low Stock' : 'In Stock',
            'status_tone' => $p->stock_qty <= $p->reorder_level ? 'danger' : 'success',
        ])->values();

        return [
            'type' => 'products',
            'title' => $slow ? 'Slowest-Moving Products (Last 30 Days)' : 'Products',
            'rows' => $rows,
        ];
    }

    private function purchaseOrdersWidget(): array
    {
        $orders = PurchaseOrder::with('supplier')->where('status', 'pending')->orderByDesc('order_date')->limit(10)->get();

        return [
            'type' => 'purchase_orders',
            'title' => 'Pending Purchase Orders',
            'rows' => $orders->map(fn ($po) => [
                'supplier' => $po->supplier->name,
                'order_date' => $po->order_date->format('Y-m-d'),
                'total' => number_format($po->total_amount, 2),
                'status' => ucfirst($po->status),
            ])->values(),
        ];
    }

    private function customersWidget(): array
    {
        $customers = Customer::withSum('sales', 'total')->orderByDesc('sales_sum_total')->limit(10)->get();

        return [
            'type' => 'customers',
            'title' => 'Top Customers',
            'rows' => $customers->map(fn ($c) => [
                'name' => $c->name,
                'phone' => $c->phone,
                'total_spent' => number_format($c->sales_sum_total ?? 0, 2),
                'points_balance' => $c->points_balance,
            ])->values(),
        ];
    }

    private function buildCashierContext(): string
    {
        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get(['name', 'sku', 'barcode', 'selling_price', 'stock_qty'])
            ->map(fn ($p) => "{$p->name} | SKU: {$p->sku} | Barcode: {$p->barcode} | Price: {$p->selling_price} | Stock: {$p->stock_qty}")
            ->implode("\n");

        return "PRODUCT CATALOG (name | SKU | barcode | price | stock on hand):\n{$products}";
    }

    private function buildAdminContext(): string
    {
        $catalog = $this->buildCashierContext();

        $last30Days = DB::table('sales')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('COUNT(*) as transactions, COALESCE(SUM(total), 0) as revenue')
            ->first();

        $lowStock = Product::whereColumn('stock_qty', '<=', 'reorder_level')
            ->where('is_active', true)
            ->get(['name', 'stock_qty', 'reorder_level'])
            ->map(fn ($p) => "{$p->name} (stock: {$p->stock_qty}, reorder level: {$p->reorder_level})")
            ->implode("\n") ?: 'None';

        $reorderRecommendations = $this->forecastService->forecastAll()
            ->filter(fn ($f) => $f['needs_reorder'])
            ->map(fn ($f) => "{$f['product']->name}: recommend ordering {$f['recommended_reorder_qty']} units (forecasted 30-day demand: {$f['forecast_30d']})")
            ->implode("\n") ?: 'None';

        $topCustomers = Customer::withSum('sales', 'total')
            ->orderByDesc('sales_sum_total')
            ->limit(5)
            ->get()
            ->map(fn ($c) => "{$c->name}: total spent {$c->sales_sum_total}, {$c->points_balance} star points")
            ->implode("\n") ?: 'None';

        $pendingOrders = PurchaseOrder::with('supplier')->where('status', 'pending')
            ->get()
            ->map(fn ($po) => "{$po->supplier->name}: ordered {$po->order_date->format('Y-m-d')}, total {$po->total_amount}")
            ->implode("\n") ?: 'None';

        return "{$catalog}\n\n".
            "SALES SUMMARY (last 30 days): {$last30Days->transactions} transactions, revenue {$last30Days->revenue}\n\n".
            "LOW STOCK PRODUCTS:\n{$lowStock}\n\n".
            "REORDER RECOMMENDATIONS:\n{$reorderRecommendations}\n\n".
            "TOP CUSTOMERS BY TOTAL SPEND:\n{$topCustomers}\n\n".
            "PENDING PURCHASE ORDERS:\n{$pendingOrders}";
    }
}
