<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ __('Forecast: ').$product->name }}</h2>
            <a href="{{ route('admin.forecasts.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
        </div>
    </x-slot>

    <div class="card mb-3">
        <div class="card-body">
            <h3 class="h6">{{ __('AI Explanation') }}</h3>
            @if ($narrative)
                <p class="mb-0">{{ $narrative }}</p>
            @else
                <div class="alert alert-secondary mb-0">
                    {{ __('AI narrative is currently unavailable (Gemini API not configured or unreachable). Showing computed data below.') }}
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">{{ __('Avg Weekly Sales') }}</div>
                    <div class="fs-5">{{ $forecast['avg_weekly_qty'] }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">{{ __('Forecast (7 days)') }}</div>
                    <div class="fs-5">{{ $forecast['forecast_7d'] }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">{{ __('Forecast (30 days)') }}</div>
                    <div class="fs-5">{{ $forecast['forecast_30d'] }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">{{ __('Current Stock') }}</div>
                    <div class="fs-5">{{ $product->stock_qty }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">{{ __('Reorder Level') }}</div>
                    <div class="fs-5">{{ $product->reorder_level }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">{{ __('Projected Stock (30d)') }}</div>
                    <div class="fs-5">{{ $forecast['projected_stock_30d'] }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">{{ __('Recommended Reorder Qty') }}</div>
                    <div class="fs-5 {{ $forecast['needs_reorder'] ? 'text-danger fw-bold' : '' }}">{{ $forecast['recommended_reorder_qty'] }}</div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
