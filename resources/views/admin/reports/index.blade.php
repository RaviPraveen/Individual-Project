<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h2 class="h3 mb-0 fw-extrabold text-dark"><i class="bi bi-bar-chart-line text-primary me-2"></i>{{ __('Business Reports Hub') }}</h2>
                <div class="text-muted small">{{ __('Analyze revenue trends, category performance, cashier shifts, profit margins, and inventory metrics.') }}</div>
            </div>
        </div>
    </x-slot>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm card-hover">
                <div class="card-body p-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-3 p-3 mb-3" style="width:48px;height:48px;">
                        <i class="bi bi-graph-up-arrow fs-4"></i>
                    </div>
                    <h3 class="h5 fw-bold text-dark mb-2">{{ __('Sales Summary') }}</h3>
                    <p class="text-muted small mb-4">{{ __('Daily, weekly, or custom date range revenue breakdown and transaction counts.') }}</p>
                    <a href="{{ route('admin.reports.sales') }}" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-bold">{{ __('Open Report') }} &rarr;</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm card-hover">
                <div class="card-body p-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-success-subtle text-success rounded-3 p-3 mb-3" style="width:48px;height:48px;">
                        <i class="bi bi-box-seam fs-4"></i>
                    </div>
                    <h3 class="h5 fw-bold text-dark mb-2">{{ __('Sales by Product') }}</h3>
                    <p class="text-muted small mb-4">{{ __('Quantity sold, total revenue generated, and top velocity products.') }}</p>
                    <a href="{{ route('admin.reports.by-product') }}" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-bold">{{ __('Open Report') }} &rarr;</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm card-hover">
                <div class="card-body p-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-info-subtle text-info rounded-3 p-3 mb-3" style="width:48px;height:48px;">
                        <i class="bi bi-tags fs-4"></i>
                    </div>
                    <h3 class="h5 fw-bold text-dark mb-2">{{ __('Sales by Category') }}</h3>
                    <p class="text-muted small mb-4">{{ __('Revenue distribution across store categories for merchandising decisions.') }}</p>
                    <a href="{{ route('admin.reports.by-category') }}" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-bold">{{ __('Open Report') }} &rarr;</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm card-hover">
                <div class="card-body p-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-purple-subtle text-purple rounded-3 p-3 mb-3" style="width:48px;height:48px;background:#F3E8FF;color:#7E22CE;">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                    <h3 class="h5 fw-bold text-dark mb-2">{{ __('Sales by Cashier') }}</h3>
                    <p class="text-muted small mb-4">{{ __('Performance, transaction counts, and totals per cashier team member.') }}</p>
                    <a href="{{ route('admin.reports.by-cashier') }}" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-bold">{{ __('Open Report') }} &rarr;</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm card-hover">
                <div class="card-body p-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-warning-subtle text-warning-emphasis rounded-3 p-3 mb-3" style="width:48px;height:48px;">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </div>
                    <h3 class="h5 fw-bold text-dark mb-2">{{ __('Low Stock Audit') }}</h3>
                    <p class="text-muted small mb-4">{{ __('Products currently at or below their designated reorder threshold.') }}</p>
                    <a href="{{ route('admin.reports.low-stock') }}" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-bold">{{ __('Open Report') }} &rarr;</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm card-hover">
                <div class="card-body p-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-3 p-3 mb-3" style="width:48px;height:48px;">
                        <i class="bi bi-percent fs-4"></i>
                    </div>
                    <h3 class="h5 fw-bold text-dark mb-2">{{ __('Profit & Gross Margin') }}</h3>
                    <p class="text-muted small mb-4">{{ __('Cost price vs selling price analysis and margin calculations per product.') }}</p>
                    <a href="{{ route('admin.reports.profit') }}" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-bold">{{ __('Open Report') }} &rarr;</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm card-hover">
                <div class="card-body p-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-3 p-3 mb-3" style="width:48px;height:48px;">
                        <i class="bi bi-hourglass-split fs-4"></i>
                    </div>
                    <h3 class="h5 fw-bold text-dark mb-2">{{ __('Near-Expiry Stock') }}</h3>
                    <p class="text-muted small mb-4">{{ __('Perishable products approaching their expiry date, soonest first.') }}</p>
                    <a href="{{ route('admin.reports.near-expiry') }}" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-bold">{{ __('Open Report') }} &rarr;</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm card-hover">
                <div class="card-body p-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-secondary-subtle text-secondary rounded-3 p-3 mb-3" style="width:48px;height:48px;">
                        <i class="bi bi-box-seam fs-4"></i>
                    </div>
                    <h3 class="h5 fw-bold text-dark mb-2">{{ __('Dead-Stock / Slow-Moving') }}</h3>
                    <p class="text-muted small mb-4">{{ __('Products not selling — the strongest candidates to return to their supplier.') }}</p>
                    <a href="{{ route('admin.reports.dead-stock') }}" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-bold">{{ __('Open Report') }} &rarr;</a>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
