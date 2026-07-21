<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiLog;
use App\Models\Supplier;
use App\Services\ForecastService;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReorderController extends Controller
{
    public function __construct(
        private ForecastService $forecastService,
        private AiService $gemini,
    ) {}

    public function index(Request $request): View
    {
        $suggestions = $this->forecastService->forecastAll()
            ->filter(fn ($f) => $f['needs_reorder'])
            ->sortBy('projected_stock_30d')
            ->values();

        $usualSuppliers = $this->usualSuppliers($suggestions->pluck('product.id')->all());

        $suggestions = $suggestions->map(function ($f) use ($usualSuppliers) {
            $f['usual_supplier'] = $usualSuppliers[$f['product']->id] ?? null;

            return $f;
        });

        return view('admin.reorder.index', [
            'suggestions' => $suggestions,
            'suppliers' => Supplier::orderBy('name')->get(),
            'narrative' => $this->narrative($request, $suggestions),
            'geminiConfigured' => $this->gemini->isConfigured(),
        ]);
    }

    /**
     * The most recent supplier a product was ordered from, based on past
     * purchase order history. Informational only — the admin still chooses
     * which supplier to draft a new order against.
     */
    private function usualSuppliers(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $rows = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->whereIn('purchase_order_items.product_id', $productIds)
            ->orderByDesc('purchase_orders.order_date')
            ->orderByDesc('purchase_orders.id')
            ->get(['purchase_order_items.product_id', 'suppliers.id as supplier_id', 'suppliers.name as supplier_name']);

        $result = [];

        foreach ($rows as $row) {
            if (! isset($result[$row->product_id])) {
                $result[$row->product_id] = ['id' => $row->supplier_id, 'name' => $row->supplier_name];
            }
        }

        return $result;
    }

    /**
     * Cached briefly so repeated visits don't re-call the AI service. See
     * DashboardController::businessSummary() for why a null narrative is
     * cached as '' rather than a literal null.
     */
    private function narrative(Request $request, $suggestions): ?string
    {
        if ($suggestions->isEmpty()) {
            return null;
        }

        $cacheKey = 'reorder_narrative_'.$suggestions->count().'_'.now()->format('Y_m_d_H').'_'.intdiv(now()->minute, 30);

        $cached = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($request, $suggestions) {
            $list = $suggestions->map(fn ($f) => "{$f['product']->name}: stock {$f['product']->stock_qty}, reorder level {$f['product']->reorder_level}, ".
                "30-day forecast {$f['forecast_30d']}, recommended order qty {$f['recommended_reorder_qty']}")->implode("\n");

            $prompt = 'You are a retail purchasing analyst for a Sri Lankan supermarket called Welcome Foodcity. '.
                'Based ONLY on the following computed stock and demand data, write a short (3-4 sentence) '.
                'purchasing recommendation: which products are most urgent to reorder and roughly why. '.
                "Do not invent any numbers beyond what is given.\n\n{$list}";

            $narrative = $this->gemini->generate($prompt);

            AiLog::create([
                'user_id' => $request->user()->id,
                'query' => 'Smart Reorder Assistant recommendation ('.now()->format('Y-m-d H:i').')',
                'response' => $narrative ?? '[AI unavailable — narrative not generated]',
            ]);

            return $narrative ?? '';
        });

        return $cached === '' ? null : $cached;
    }
}
