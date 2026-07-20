<x-admin-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Welcome back, :name', ['name' => explode(' ', auth()->user()->name)[0]]) }}</h2>
        <div class="text-muted small">{{ __('Here\'s a quick look at the store right now.') }}</div>
    </x-slot>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-cash-stack" tone="success" :label="__('Today\'s Sales')" :value="number_format($stats['today_total'], 2)" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-graph-up" tone="primary" :label="__('This Month\'s Sales')" :value="number_format($stats['month_total'], 2)" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-receipt" tone="info" :label="__('Bills Today')" :value="$stats['today_count']" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-exclamation-triangle" tone="warning" :label="__('Low Stock')" :value="$stats['low_stock']" />
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-stars me-1 text-gold"></i> {{ __('AI Business Summary') }}</div>
                <div class="card-body">
                    @if ($summary)
                        <p class="mb-0">{{ $summary }}</p>
                    @else
                        <div class="alert alert-secondary mb-0">
                            {{ __('AI narrative is currently unavailable (Gemini API not configured or unreachable). Showing figures below instead.') }}
                        </div>
                    @endif
                    <div class="text-muted small mt-2">{{ __('Refreshes automatically every 30 minutes.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-tags me-1"></i> {{ __('Top Category This Month') }}</div>
                <div class="card-body d-flex flex-column justify-content-center h-100">
                    @if ($topCategory)
                        <div class="fs-4 fw-bold">{{ $topCategory->name }}</div>
                        <div class="text-muted small">{{ __(':qty units sold', ['qty' => $topCategory->qty]) }}</div>
                    @else
                        <div class="text-muted small">{{ __('No sales recorded this month yet.') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-graph-up-arrow me-1"></i> {{ __('Sales — Last 14 Days') }}</div>
                <div class="card-body">
                    <div class="pos-chart-wrap">
                        <canvas id="salesTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-pie-chart me-1"></i> {{ __('Revenue by Category (Month)') }}</div>
                <div class="card-body">
                    <div class="pos-chart-wrap" style="height: 220px;">
                        @if (count($categoryBreakdown['labels']) > 0)
                            <canvas id="categoryChart"></canvas>
                        @else
                            <x-empty-state icon="bi-pie-chart" :title="__('No sales yet this month')" />
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-box-seam" tone="primary" :label="__('Products')" :value="$stats['products']" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-truck" tone="info" :label="__('Suppliers')" :value="$stats['suppliers']" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-people" tone="success" :label="__('Customers')" :value="$stats['customers']" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-arrow-return-left" tone="warning" :label="__('Refunds This Month')" :value="number_format($stats['refunds_month_total'], 2)" />
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
        const trendLabels = @json($salesTrend['labels']);
        const trendData = @json($salesTrend['data']);

        new Chart(document.getElementById('salesTrendChart'), {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: '{{ __('Sales') }}',
                    data: trendData,
                    borderColor: '#146C43',
                    backgroundColor: 'rgba(20, 108, 67, 0.10)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    borderWidth: 2,
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#E3E7E2' }, ticks: { callback: (v) => v.toLocaleString() } },
                    x: { grid: { display: false } },
                },
            },
        });

        @if (count($categoryBreakdown['labels']) > 0)
        const categoryLabels = @json($categoryBreakdown['labels']);
        const categoryData = @json($categoryBreakdown['data']);
        const categoryColors = ['#146C43', '#B8862E', '#2563A8', '#C23B3B', '#66716B', '#8F6822'];

        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{ data: categoryData, backgroundColor: categoryColors, borderWidth: 2, borderColor: '#fff' }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
            },
        });
        @endif
    </script>
</x-admin-layout>
