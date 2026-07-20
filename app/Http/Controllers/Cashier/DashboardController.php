<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\AiLog;
use App\Services\CashierDashboardService;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private CashierDashboardService $dashboard,
        private GeminiService $gemini,
    ) {}

    public function index(Request $request): View
    {
        $cashier = $request->user();

        $stats = $this->dashboard->statsToday($cashier);
        $paymentSummary = $this->dashboard->paymentSummaryToday($cashier);
        $todaysSales = $this->dashboard->myTodaysSales($cashier);
        $recentTransactions = $this->dashboard->recentTransactions($cashier, 10);
        $lowStock = $this->dashboard->lowStockProducts(8);

        $notices = [];
        if ($lowStock->isNotEmpty()) {
            $outOfStock = $lowStock->where('stock_qty', 0)->count();
            if ($outOfStock > 0) {
                $notices[] = "{$outOfStock} product(s) are completely out of stock.";
            }
            $notices[] = "{$lowStock->count()} product(s) are at or below their reorder level.";
        }
        if ($stats['bills_processed'] === 0) {
            $notices[] = 'No sales recorded yet today — have a great shift!';
        }

        $shift = match (true) {
            now()->hour < 12 => 'Morning Shift',
            now()->hour < 18 => 'Afternoon Shift',
            default => 'Evening Shift',
        };

        $aiLogs = AiLog::where('user_id', $cashier->id)->latest()->take(3)->get();

        return view('cashier.dashboard', [
            'cashier' => $cashier,
            'stats' => $stats,
            'paymentSummary' => $paymentSummary,
            'todaysSales' => $todaysSales,
            'recentTransactions' => $recentTransactions,
            'lowStock' => $lowStock,
            'notices' => $notices,
            'shift' => $shift,
            'aiLogs' => $aiLogs,
            'geminiConfigured' => $this->gemini->isConfigured(),
        ]);
    }
}
