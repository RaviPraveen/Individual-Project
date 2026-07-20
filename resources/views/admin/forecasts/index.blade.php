<x-admin-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Sales Forecast & Stock Prediction') }}</h2>
    </x-slot>

    <p class="text-muted small">{{ __('Based on average weekly sales over the last 8 weeks.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Avg Weekly Sales') }}</th>
                        <th>{{ __('Forecast (7d)') }}</th>
                        <th>{{ __('Forecast (30d)') }}</th>
                        <th>{{ __('Current Stock') }}</th>
                        <th>{{ __('Projected Stock (30d)') }}</th>
                        <th>{{ __('Reorder Qty') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($forecasts as $f)
                        <tr class="{{ $f['needs_reorder'] ? 'table-warning' : '' }}">
                            <td>{{ $f['product']->name }}</td>
                            <td>{{ $f['avg_weekly_qty'] }}</td>
                            <td>{{ $f['forecast_7d'] }}</td>
                            <td>{{ $f['forecast_30d'] }}</td>
                            <td>{{ $f['product']->stock_qty }}</td>
                            <td>{{ $f['projected_stock_30d'] }}</td>
                            <td>{{ $f['recommended_reorder_qty'] }}</td>
                            <td><a href="{{ route('admin.forecasts.show', $f['product']) }}" class="btn btn-outline-primary btn-sm">{{ __('Details') }}</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-0"><x-empty-state icon="bi-graph-up-arrow" :title="__('No active products to forecast')" /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
