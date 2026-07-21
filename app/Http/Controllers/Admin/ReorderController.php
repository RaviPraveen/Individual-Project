<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiLog;
use App\Models\Supplier;
use App\Services\AiService;
use App\Services\ForecastService;
use App\Services\ProductSupplierResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ReorderController extends Controller
{
    public function __construct(
        private ForecastService $forecastService,
        private AiService $gemini,
        private ProductSupplierResolver $supplierResolver,
    ) {}

    public function index(Request $request): View
    {
        $suggestions = $this->forecastService->forecastAll()
            ->filter(fn ($f) => $f['needs_reorder'])
            ->sortBy('projected_stock_30d')
            ->values();

        $usualSuppliers = $this->supplierResolver->resolve($suggestions->pluck('product.id')->all());

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
