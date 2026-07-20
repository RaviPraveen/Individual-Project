<x-admin-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Reports') }}</h2>
    </x-slot>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h6">{{ __('Sales Summary') }}</h3>
                    <p class="text-muted small">{{ __('Daily, weekly, or monthly totals for a date range.') }}</p>
                    <a href="{{ route('admin.reports.sales') }}" class="btn btn-outline-primary btn-sm">{{ __('View') }}</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h6">{{ __('Sales by Product') }}</h3>
                    <p class="text-muted small">{{ __('Quantity and revenue per product.') }}</p>
                    <a href="{{ route('admin.reports.by-product') }}" class="btn btn-outline-primary btn-sm">{{ __('View') }}</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h6">{{ __('Sales by Category') }}</h3>
                    <p class="text-muted small">{{ __('Quantity and revenue per category.') }}</p>
                    <a href="{{ route('admin.reports.by-category') }}" class="btn btn-outline-primary btn-sm">{{ __('View') }}</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h6">{{ __('Sales by Cashier') }}</h3>
                    <p class="text-muted small">{{ __('Transactions and revenue per cashier.') }}</p>
                    <a href="{{ route('admin.reports.by-cashier') }}" class="btn btn-outline-primary btn-sm">{{ __('View') }}</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h6">{{ __('Low Stock') }}</h3>
                    <p class="text-muted small">{{ __('Products at or below their reorder level.') }}</p>
                    <a href="{{ route('admin.reports.low-stock') }}" class="btn btn-outline-primary btn-sm">{{ __('View') }}</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h6">{{ __('Profit / Margin') }}</h3>
                    <p class="text-muted small">{{ __('Cost vs. selling price by product.') }}</p>
                    <a href="{{ route('admin.reports.profit') }}" class="btn btn-outline-primary btn-sm">{{ __('View') }}</a>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
