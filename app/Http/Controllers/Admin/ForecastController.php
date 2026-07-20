<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiLog;
use App\Models\Product;
use App\Services\ForecastService;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForecastController extends Controller
{
    public function __construct(
        private ForecastService $forecastService,
        private GeminiService $gemini,
    ) {}

    public function index(): View
    {
        $forecasts = $this->forecastService->forecastAll();

        return view('admin.forecasts.index', compact('forecasts'));
    }

    public function show(Request $request, Product $product): View
    {
        $forecast = $this->forecastService->forecastForProduct($product);

        $prompt = "You are an inventory analyst for a Sri Lankan supermarket called Welcome Foodcity. ".
            "Based ONLY on the following computed data, write a short (2-3 sentence) plain-language ".
            "explanation of the sales trend and stock outlook for this product. Do not invent any numbers ".
            "beyond what is given.\n\n".
            "Product: {$product->name}\n".
            "Quantity sold in the last {$forecast['lookback_weeks']} weeks: {$forecast['total_qty_sold']}\n".
            "Average weekly sales: {$forecast['avg_weekly_qty']}\n".
            "Forecasted demand next 7 days: {$forecast['forecast_7d']}\n".
            "Forecasted demand next 30 days: {$forecast['forecast_30d']}\n".
            "Current stock on hand: {$product->stock_qty}\n".
            "Reorder level: {$product->reorder_level}\n".
            "Projected stock after 30 days: {$forecast['projected_stock_30d']}\n".
            "Recommended reorder quantity: {$forecast['recommended_reorder_qty']}";

        $narrative = $this->gemini->generate($prompt);

        AiLog::create([
            'user_id' => $request->user()->id,
            'query' => "Sales forecast narrative for product #{$product->id} ({$product->name})",
            'response' => $narrative ?? '[AI unavailable — narrative not generated]',
        ]);

        return view('admin.forecasts.show', [
            'product' => $product,
            'forecast' => $forecast,
            'narrative' => $narrative,
        ]);
    }
}
