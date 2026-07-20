<?php

namespace App\Http\Controllers;

use App\Models\AiLog;
use App\Models\Product;
use App\Services\ForecastService;
use App\Services\GeminiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AiChatController extends Controller
{
    public function __construct(
        private GeminiService $gemini,
        private ForecastService $forecastService,
    ) {}

    public function index(Request $request): View
    {
        $logs = AiLog::where('user_id', $request->user()->id)->latest()->take(10)->get();
        $view = $request->user()->role === 'admin' ? 'admin.ai-chat.index' : 'cashier.ai-chat.index';
        $geminiConfigured = $this->gemini->isConfigured();

        return view($view, compact('logs', 'geminiConfigured'));
    }

    public function ask(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $isAdmin = $request->user()->role === 'admin';
        $context = $isAdmin ? $this->buildAdminContext() : $this->buildCashierContext();

        $prompt = "You are an internal staff assistant for Welcome Foodcity, a supermarket in Batticaloa, ".
            "Sri Lanka. Answer ONLY using the data provided below. Do not invent prices, stock levels, or ".
            "figures that are not present in this data. If the question cannot be answered from this data, ".
            "say so plainly.\n\nDATA:\n{$context}\n\nSTAFF QUESTION: {$validated['message']}";

        $answer = $this->gemini->generate($prompt);

        AiLog::create([
            'user_id' => $request->user()->id,
            'query' => $validated['message'],
            'response' => $answer ?? 'The AI assistant is currently unavailable. Please try again later.',
        ]);

        return redirect()->back();
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

        return "{$catalog}\n\n".
            "SALES SUMMARY (last 30 days): {$last30Days->transactions} transactions, revenue {$last30Days->revenue}\n\n".
            "LOW STOCK PRODUCTS:\n{$lowStock}\n\n".
            "REORDER RECOMMENDATIONS:\n{$reorderRecommendations}";
    }
}
