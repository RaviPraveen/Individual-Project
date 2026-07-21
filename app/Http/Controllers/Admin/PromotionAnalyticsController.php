<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\PromotionAnalytics;
use Illuminate\View\View;

class PromotionAnalyticsController extends Controller
{
    public function index(PromotionAnalytics $analytics): View
    {
        Promotion::syncDueStatuses();

        $rows = $analytics->allWithMetrics()->sortByDesc(fn ($row) => $row['metrics']['revenue'])->values();

        return view('admin.promotions.analytics', [
            'rows' => $rows,
            'best' => $rows->take(5),
            'totals' => [
                'display_count' => $rows->sum(fn ($row) => $row['metrics']['display_count']),
                'units_sold' => $rows->sum(fn ($row) => $row['metrics']['units_sold']),
                'revenue' => $rows->sum(fn ($row) => $row['metrics']['revenue']),
            ],
        ]);
    }
}
