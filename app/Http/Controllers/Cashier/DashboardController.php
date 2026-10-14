<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\AiLog;
use App\Services\CashierDashboardService;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private CashierDashboardService $dashboardService,
        private AiService $aiService,
    ) {}

    public function index(Request $request): View
    {
        $cashier = $request->user();

        $stats = $this->dashboardService->statsToday($cashier);
        $paymentSummary = $this->dashboardService->paymentSummaryToday($cashier);
        $todaysSales = $this->dashboardService->myTodaysSales($cashier);
        $recentTransactions = $this->dashboardService->recentTransactions($cashier, 10);
        $lowStock = $this->dashboardService->lowStockProducts(8);

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
            'aiConfigured' => $this->aiService->isConfigured(),
        ]);
    }
}
