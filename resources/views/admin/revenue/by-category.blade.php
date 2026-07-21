<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ __('Revenue by Category') }}</h2>
            <a href="{{ route('admin.revenue.by-category', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i>{{ __('Export CSV') }}</a>
        </div>
    </x-slot>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.revenue.by-category') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <x-input-label for="start_date" :value="__('From')" />
                    <x-text-input id="start_date" name="start_date" type="date" value="{{ $start }}" />
                </div>
                <div class="col-md-4">
                    <x-input-label for="end_date" :value="__('To')" />
                    <x-text-input id="end_date" name="end_date" type="date" value="{{ $end }}" />
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-secondary w-100">{{ __('Apply') }}</button>
                </div>
                <input type="hidden" name="sort" value="{{ $sort }}">
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-bold text-dark border-bottom py-3">
            <i class="bi bi-bar-chart me-1.5 text-primary"></i> {{ __('Revenue by Category') }}
        </div>
        <div class="card-body">
            <div class="pos-chart-wrap" style="height: 300px;">
                @if ($rows->isNotEmpty())
                    <canvas id="categoryBarChart"></canvas>
                @else
                    <x-empty-state icon="bi-bar-chart" :title="__('No sales in this range')" />
                @endif
            </div>
        </div>
    </div>

    @php
        $sortLink = fn (string $key) => route('admin.revenue.by-category', array_merge(request()->query(), ['sort' => $key]));
        $sortIcon = fn (string $key) => $sort === $key ? 'bi-caret-down-fill text-primary' : 'bi-caret-down text-muted';
    @endphp

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Category') }}</th>
                        <th class="text-end">{{ __('Units Sold') }}</th>
                        <th class="text-end"><a href="{{ $sortLink('revenue') }}" class="text-decoration-none text-dark">{{ __('Revenue') }} <i class="bi {{ $sortIcon('revenue') }}"></i></a></th>
                        <th class="text-end">{{ __('Cost') }}</th>
                        <th class="text-end"><a href="{{ $sortLink('profit') }}" class="text-decoration-none text-dark">{{ __('Profit') }} <i class="bi {{ $sortIcon('profit') }}"></i></a></th>
                        <th class="text-end"><a href="{{ $sortLink('margin') }}" class="text-decoration-none text-dark">{{ __('Margin %') }} <i class="bi {{ $sortIcon('margin') }}"></i></a></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="{{ $row->margin_percent < $lowMarginThreshold ? 'table-danger' : '' }}">
                            <td class="fw-semibold text-dark">
                                {{ $row->name }}
                                @if ($row->margin_percent < $lowMarginThreshold)
                                    <span class="badge bg-danger ms-1">{{ __('Low Margin') }}</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $row->qty_sold }}</td>
                            <td class="text-end">Rs {{ number_format($row->revenue, 2) }}</td>
                            <td class="text-end">Rs {{ number_format($row->cost, 2) }}</td>
                            <td class="text-end">Rs {{ number_format($row->profit, 2) }}</td>
                            <td class="text-end">{{ number_format($row->margin_percent, 1) }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-0"><x-empty-state icon="bi-tags" :title="__('No sales in this range')" /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($rows->isNotEmpty())
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
        <script>
            new Chart(document.getElementById('categoryBarChart'), {
                type: 'bar',
                data: {
                    labels: @json($rows->pluck('name')),
                    datasets: [{
                        label: '{{ __('Revenue') }}',
                        data: @json($rows->pluck('revenue')),
                        backgroundColor: '#4F46E5',
                        borderRadius: 6,
                        maxBarThickness: 48,
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
        </script>
    @endif
</x-admin-layout>
