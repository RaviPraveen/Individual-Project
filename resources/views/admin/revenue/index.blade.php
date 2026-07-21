<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h2 class="h3 mb-0 fw-extrabold text-dark"><i class="bi bi-cash-coin text-primary me-2"></i>{{ __('Revenue Overview') }}</h2>
                <div class="text-muted small">{{ __('Revenue, gross profit, and margin across every period, compared to the equivalent period before it.') }}</div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.revenue.by-period') }}" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-calendar-range"></i> {{ __('By Period') }}</a>
                <a href="{{ route('admin.revenue.by-product') }}" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-box-seam"></i> {{ __('By Product') }}</a>
                <a href="{{ route('admin.revenue.by-category') }}" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-tags"></i> {{ __('By Category') }}</a>
            </div>
        </div>
    </x-slot>

    @php
        $periods = [
            ['key' => 'today', 'label' => __('Today'), 'compare' => __('vs yesterday'), 'data' => $today, 'icon' => 'bi-sun', 'tone' => 'primary'],
            ['key' => 'week', 'label' => __('This Week'), 'compare' => __('vs last week'), 'data' => $week, 'icon' => 'bi-calendar-week', 'tone' => 'info'],
            ['key' => 'month', 'label' => __('This Month'), 'compare' => __('vs last month'), 'data' => $month, 'icon' => 'bi-calendar3', 'tone' => 'success'],
            ['key' => 'year', 'label' => __('This Year'), 'compare' => __('vs last year'), 'data' => $year, 'icon' => 'bi-calendar-heart', 'tone' => 'warning'],
        ];
        $toneMap = [
            'primary' => ['bg' => '#EEF2FF', 'fg' => '#4F46E5'],
            'info' => ['bg' => '#EFF6FF', 'fg' => '#2563EB'],
            'success' => ['bg' => '#ECFDF5', 'fg' => '#059669'],
            'warning' => ['bg' => '#FFFBEB', 'fg' => '#D97706'],
        ];
    @endphp

    <div class="row g-3 mb-4">
        @foreach ($periods as $period)
            @php $tone = $toneMap[$period['tone']]; @endphp
            <div class="col-md-6 col-xl-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-3.5">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:40px;height:40px;background:{{ $tone['bg'] }};color:{{ $tone['fg'] }};">
                                <i class="bi {{ $period['icon'] }} fs-5"></i>
                            </div>
                            <span class="badge {{ $period['data']['trend'] === 'up' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} rounded-pill">
                                <i class="bi {{ $period['data']['trend'] === 'up' ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
                                {{ number_format(abs($period['data']['change_percent']), 1) }}%
                            </span>
                        </div>
                        <div class="text-uppercase text-muted fw-bold small" style="letter-spacing:0.05em;font-size:0.7rem;">{{ $period['label'] }} {{ __('Revenue') }}</div>
                        <div class="h3 fw-extrabold text-dark num-tabular mb-1">Rs {{ number_format($period['data']['revenue'], 2) }}</div>
                        <div class="text-muted small mb-3">{{ $period['compare'] }}</div>
                        <div class="d-flex justify-content-between pt-2 border-top">
                            <div>
                                <div class="text-muted small">{{ __('Gross Profit') }}</div>
                                <div class="fw-bold text-dark">Rs {{ number_format($period['data']['profit'], 2) }}</div>
                            </div>
                            <div class="text-end">
                                <div class="text-muted small">{{ __('Margin') }}</div>
                                <div class="fw-bold {{ $period['data']['margin_percent'] < 10 ? 'text-danger' : 'text-dark' }}">{{ number_format($period['data']['margin_percent'], 1) }}%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold text-dark border-bottom py-3">
                    <i class="bi bi-graph-up me-1.5 text-primary"></i> {{ __('Monthly Revenue Trend — Last 12 Months') }}
                </div>
                <div class="card-body">
                    <div class="pos-chart-wrap" style="height: 280px;">
                        <canvas id="monthlyTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold text-dark border-bottom py-3">
                    <i class="bi bi-pie-chart me-1.5 text-primary"></i> {{ __('Revenue by Category — This Month') }}
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div class="pos-chart-wrap w-100" style="height: 260px;">
                        @if ($categoryBreakdown->isNotEmpty())
                            <canvas id="categoryRevenueChart"></canvas>
                        @else
                            <x-empty-state icon="bi-pie-chart" :title="__('No sales data')" :text="__('Category revenue chart will render after sales.')" />
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold text-dark border-bottom py-3">
            <i class="bi bi-trophy me-1.5 text-gold"></i> {{ __('Top 5 Revenue-Generating Products — This Month') }}
        </div>
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th class="text-end">{{ __('Units Sold') }}</th>
                        <th class="text-end">{{ __('Revenue') }}</th>
                        <th class="text-end">{{ __('Profit') }}</th>
                        <th class="text-end">{{ __('Margin %') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($topProducts as $row)
                        <tr>
                            <td class="fw-semibold text-dark">{{ $row->name }}</td>
                            <td class="text-end">{{ $row->qty_sold }}</td>
                            <td class="text-end">Rs {{ number_format($row->revenue, 2) }}</td>
                            <td class="text-end">Rs {{ number_format($row->profit, 2) }}</td>
                            <td class="text-end">{{ number_format($row->margin_percent, 1) }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-0"><x-empty-state icon="bi-trophy" :title="__('No sales this month yet')" /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
        new Chart(document.getElementById('monthlyTrendChart'), {
            type: 'bar',
            data: {
                labels: @json($monthlyTrend['labels']),
                datasets: [{
                    label: '{{ __('Revenue') }}',
                    data: @json($monthlyTrend['data']),
                    backgroundColor: '#4F46E5',
                    borderRadius: 6,
                    maxBarThickness: 36,
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#F1F5F9' }, ticks: { callback: (v) => v.toLocaleString() } },
                    x: { grid: { display: false } },
                },
            },
        });

        @if ($categoryBreakdown->isNotEmpty())
        const categoryColors = ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#3B82F6', '#8B5CF6', '#EC4899', '#14B8A6'];

        new Chart(document.getElementById('categoryRevenueChart'), {
            type: 'doughnut',
            data: {
                labels: @json($categoryBreakdown->pluck('name')),
                datasets: [{ data: @json($categoryBreakdown->pluck('revenue')), backgroundColor: categoryColors, borderWidth: 3, borderColor: '#fff' }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11, family: 'Inter' } } } },
            },
        });
        @endif
    </script>
</x-admin-layout>
