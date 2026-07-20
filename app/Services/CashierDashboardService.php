<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CashierDashboardService
{
    public function statsToday(User $cashier): array
    {
        $todaySales = Sale::where('cashier_id', $cashier->id)->whereDate('created_at', today());

        $billsProcessed = (clone $todaySales)->count();
        $salesTotal = (float) (clone $todaySales)->sum('total');

        $itemsSold = (int) DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.cashier_id', $cashier->id)
            ->whereDate('sales.created_at', today())
            ->sum('sale_items.quantity');

        $customersServed = (int) DB::table('sales')
            ->where('cashier_id', $cashier->id)
            ->whereDate('created_at', today())
            ->selectRaw('COUNT(DISTINCT COALESCE(customer_id, CONCAT(\'walkin-\', id))) as c')
            ->value('c');

        return [
            'sales_total' => $salesTotal,
            'bills_processed' => $billsProcessed,
            'items_sold' => $itemsSold,
            'customers_served' => $customersServed,
        ];
    }

    public function paymentSummaryToday(User $cashier): array
    {
        $totals = Sale::where('cashier_id', $cashier->id)
            ->whereDate('created_at', today())
            ->selectRaw('payment_method, SUM(total) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        return [
            'cash' => (float) ($totals['cash'] ?? 0),
            'card' => (float) ($totals['card'] ?? 0),
            'other' => (float) ($totals['other'] ?? 0),
        ];
    }

    public function myTodaysSales(User $cashier): Collection
    {
        return Sale::with('customer')
            ->withCount('items')
            ->where('cashier_id', $cashier->id)
            ->whereDate('created_at', today())
            ->orderByDesc('created_at')
            ->get();
    }

    public function recentTransactions(User $cashier, int $limit = 10): Collection
    {
        return Sale::with('customer')
            ->withCount('items')
            ->where('cashier_id', $cashier->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function lowStockProducts(int $limit = 10): Collection
    {
        return Product::query()
            ->whereColumn('stock_qty', '<=', 'reorder_level')
            ->where('is_active', true)
            ->orderBy('stock_qty')
            ->limit($limit)
            ->get(['id', 'name', 'stock_qty', 'reorder_level', 'unit']);
    }
}
