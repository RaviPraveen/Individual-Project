<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h2 class="h3 mb-0 fw-extrabold text-dark">{{ __('Welcome back, :name 👋', ['name' => explode(' ', auth()->user()->name)[0]]) }}</h2>
                <div class="text-muted small">{{ __('Here is real-time performance summary of your store today.') }}</div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">
                    <i class="bi bi-bar-chart"></i> {{ __('Reports') }}
                </a>
                <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm rounded-pill">
                    <i class="bi bi-plus-lg"></i> {{ __('Add Product') }}
                </a>
            </div>
        </div>
    </x-slot>

    <!-- Top Key Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-cash-stack" tone="success" :label="__('Today\'s Revenue')" :value="'Rs '.number_format($stats['today_total'], 2)" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-graph-up-arrow" tone="primary" :label="__('This Month\'s Revenue')" :value="'Rs '.number_format($stats['month_total'], 2)" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-receipt" tone="info" :label="__('Bills Processed Today')" :value="$stats['today_count']" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-exclamation-triangle" tone="warning" :label="__('Low Stock Alerts')" :value="$stats['low_stock']" />
        </div>
    </div>

    <!-- AI Business Narrative Card & Top Category -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #EEF2FF 0%, #FFFFFF 100%); border: 1px solid #C7D2FE !important;">
                <div class="card-header bg-transparent fw-bold text-primary border-bottom border-primary-subtle d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-stars me-1.5 text-gold"></i> {{ __('AI Business Narrative Summary') }}</span>
                    <span class="badge bg-primary-subtle text-primary rounded-pill small">{{ __('Auto Updated') }}</span>
                </div>
                <div class="card-body">
                    @if ($summary)
                        <p class="mb-0 text-dark fw-medium leading-relaxed" style="font-size: 0.95rem;">{{ $summary }}</p>
                    @else
                        <div class="alert alert-light border mb-0 text-muted small">
                            <i class="bi bi-info-circle me-1 text-primary"></i> {{ __('AI narrative is currently generating or awaiting configuration. Financial totals are displayed in real-time below.') }}
                        </div>
                    @endif
                    <div class="text-muted small mt-3 pt-2 border-top border-primary-subtle d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-repeat"></i> {{ __('Refreshes automatically every 30 minutes with latest business metrics.') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold text-dark border-bottom-0"><i class="bi bi-trophy text-gold me-1.5"></i> {{ __('Top Category This Month') }}</div>
                <div class="card-body d-flex flex-column justify-content-center text-center p-4">
                    @if ($topCategory)
                        <div class="h2 fw-extrabold text-primary mb-1">{{ $topCategory->name }}</div>
                        <div class="badge bg-success-subtle text-success rounded-pill px-3 py-1.5 mx-auto fw-bold" style="font-size:0.85rem;">
                            <i class="bi bi-box-seam me-1"></i> {{ __(':qty units sold', ['qty' => number_format($topCategory->qty)]) }}
                        </div>
                    @else
                        <div class="text-muted small py-4">{{ __('No sales recorded yet this month.') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold text-dark border-bottom py-3 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-graph-up me-1.5 text-primary"></i> {{ __('Sales Trend — Last 14 Days') }}</span>
                    <span class="badge bg-light text-muted border">{{ __('Daily Totals') }}</span>
                </div>
                <div class="card-body">
                    <div class="pos-chart-wrap" style="height: 260px;">
                        <canvas id="salesTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold text-dark border-bottom py-3">
                    <i class="bi bi-pie-chart me-1.5 text-primary"></i> {{ __('Category Revenue') }}
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div class="pos-chart-wrap w-100" style="height: 240px;">
                        @if (count($categoryBreakdown['labels']) > 0)
                            <canvas id="categoryChart"></canvas>
                        @else
                            <x-empty-state icon="bi-pie-chart" :title="__('No sales data')" :text="__('Category sales chart will render after sales.')" />
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Counters -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-box-seam" tone="primary" :label="__('Active Products')" :value="$stats['products']" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-truck" tone="info" :label="__('Suppliers')" :value="$stats['suppliers']" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-people" tone="success" :label="__('Registered Customers')" :value="$stats['customers']" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-arrow-return-left" tone="danger" :label="__('Refunds (Month)')" :value="'Rs '.number_format($stats['refunds_month_total'], 2)" />
        </div>
    </div>

    <!-- Promotions Widgets -->
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h3 class="h6 fw-bold text-dark mb-0"><i class="bi bi-megaphone text-primary me-1.5"></i>{{ __('Promotions') }}</h3>
        <a href="{{ route('admin.promotions.index') }}" class="small fw-semibold text-decoration-none">{{ __('Open Promotion Manager') }} &rarr;</a>
    </div>
    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-broadcast" tone="success" :label="__('Active Promotions')" :value="$promotions['active']" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-clock-history" tone="info" :label="__('Scheduled Promotions')" :value="$promotions['scheduled']" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-cash-stack" tone="warning" :label="__('Promotion Revenue (Month)')" :value="'Rs '.number_format($promotions['revenue_this_month'], 2)" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-trophy" tone="primary" :label="__('Best Promotion')" :value="$promotions['best_promotion']->title ?? __('None yet')" />
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
                    borderColor: '#4F46E5',
                    backgroundColor: 'rgba(79, 70, 229, 0.08)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#4F46E5',
                    borderWidth: 3,
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

        @if (count($categoryBreakdown['labels']) > 0)
        const categoryLabels = @json($categoryBreakdown['labels']);
        const categoryData = @json($categoryBreakdown['data']);
        const categoryColors = ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#3B82F6', '#8B5CF6'];

        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{ data: categoryData, backgroundColor: categoryColors, borderWidth: 3, borderColor: '#fff' }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11, family: 'Inter' } } } },
            },
        });
        @endif
    </script>
</x-admin-layout>
