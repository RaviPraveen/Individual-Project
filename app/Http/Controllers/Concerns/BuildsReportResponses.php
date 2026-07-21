<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared by any controller building date-ranged, CSV-exportable report
 * pages (ReportController, RevenueController) — extracted so both share
 * one implementation instead of two copies drifting apart.
 */
trait BuildsReportResponses
{
    private function dateRange(Request $request): array
    {
        $start = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->subDays(30)->startOfDay();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfDay();

        return [$start, $end];
    }

    private function csv(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $header);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
