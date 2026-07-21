<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ __('Revenue by Period') }}</h2>
            <a href="{{ route('admin.revenue.by-period', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i>{{ __('Export CSV') }}</a>
        </div>
    </x-slot>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.revenue.by-period') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <x-input-label for="start_date" :value="__('From')" />
                    <x-text-input id="start_date" name="start_date" type="date" value="{{ $start }}" />
                </div>
                <div class="col-md-3">
                    <x-input-label for="end_date" :value="__('To')" />
                    <x-text-input id="end_date" name="end_date" type="date" value="{{ $end }}" />
                </div>
                <div class="col-md-3">
                    <x-input-label for="group_by" :value="__('Breakdown')" />
                    <select id="group_by" name="group_by" class="form-select">
                        <option value="day" @selected($groupBy === 'day')>{{ __('By Day') }}</option>
                        <option value="week" @selected($groupBy === 'week')>{{ __('By Week') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-secondary w-100">{{ __('Apply') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-cash-stack" tone="primary" :label="__('Total Revenue')" :value="'Rs '.number_format($totals['revenue'], 2)" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-box-seam" tone="warning" :label="__('Total Cost')" :value="'Rs '.number_format($totals['cost'], 2)" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-graph-up-arrow" tone="success" :label="__('Gross Profit')" :value="'Rs '.number_format($totals['profit'], 2)" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-percent" tone="info" :label="__('Margin %')" :value="number_format($totals['margin_percent'], 1).'%'" />
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-bold text-dark border-bottom py-3">
            <i class="bi bi-graph-up me-1.5 text-primary"></i> {{ __('Revenue vs Cost vs Profit') }}
        </div>
        <div class="card-body">
            <div class="pos-chart-wrap" style="height: 300px;">
                @if ($rows->isNotEmpty())
                    <canvas id="periodChart"></canvas>
                @else
                    <x-empty-state icon="bi-graph-up" :title="__('No sales in this range')" />
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Period') }}</th>
                        <th class="text-end">{{ __('Revenue') }}</th>
                        <th class="text-end">{{ __('Cost') }}</th>
                        <th class="text-end">{{ __('Profit') }}</th>
                        <th class="text-end">{{ __('Margin %') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->period }}</td>
                            <td class="text-end">Rs {{ number_format($row->revenue, 2) }}</td>
                            <td class="text-end">Rs {{ number_format($row->cost, 2) }}</td>
                            <td class="text-end">Rs {{ number_format($row->profit, 2) }}</td>
                            <td class="text-end">{{ number_format($row->margin_percent, 1) }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-0"><x-empty-state icon="bi-calendar-range" :title="__('No sales in this range')" /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($rows->isNotEmpty())
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
        <script>
            new Chart(document.getElementById('periodChart'), {
                type: 'line',
                data: {
                    labels: @json($rows->pluck('period')),
                    datasets: [
                        { label: '{{ __('Revenue') }}', data: @json($rows->pluck('revenue')), borderColor: '#4F46E5', backgroundColor: 'rgba(79,70,229,0.08)', tension: 0.3, borderWidth: 3 },
                        { label: '{{ __('Cost') }}', data: @json($rows->pluck('cost')), borderColor: '#F59E0B', backgroundColor: 'rgba(245,158,11,0.08)', tension: 0.3, borderWidth: 3 },
                        { label: '{{ __('Profit') }}', data: @json($rows->pluck('profit')), borderColor: '#10B981', backgroundColor: 'rgba(16,185,129,0.08)', tension: 0.3, borderWidth: 3 },
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#F1F5F9' }, ticks: { callback: (v) => v.toLocaleString() } },
                        x: { grid: { display: false } },
                    },
                },
            });
        </script>
    @endif
</x-admin-layout>
