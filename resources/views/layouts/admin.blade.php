<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Foodcity POS') }} — Admin</title>

        <!-- Fonts & Icons -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

        <!-- Centralized SaaS POS Design System -->
        <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    </head>
    <body>
        <div id="pos-flash-data" data-success="{{ session('success') }}" data-error="{{ session('error') }}" hidden></div>

        <div class="pos-shell">
            <!-- Fixed Premium Admin Vertical Sidebar -->
            <aside class="pos-sidebar">
                <a href="{{ route('admin.dashboard') }}" class="brand">
                    <span class="brand-mark">🛒</span>
                    <div class="d-flex flex-column">
                        <span class="lh-1">{{ config('app.name', 'Foodcity') }}</span>
                        <span class="fw-semibold text-uppercase mt-1" style="font-size: 0.65rem; letter-spacing: 0.1em; color: #5B5CEB;">Commercial SaaS POS</span>
                    </div>
                </a>

                <nav class="pos-nav">
                    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2"></i> {{ __('Dashboard') }}
                    </a>

                    <div class="pos-nav-label">{{ __('Catalog') }}</div>
                    <a href="{{ route('admin.categories.index') }}" class="{{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                        <i class="bi bi-tags"></i> {{ __('Categories') }}
                    </a>
                    <a href="{{ route('admin.products.index') }}" class="{{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                        <i class="bi bi-box-seam"></i> {{ __('Products') }}
                    </a>

                    <div class="pos-nav-label">{{ __('Purchasing & Stock') }}</div>
                    <a href="{{ route('admin.suppliers.index') }}" class="{{ request()->routeIs('admin.suppliers.*') ? 'active' : '' }}">
                        <i class="bi bi-truck"></i> {{ __('Suppliers') }}
                    </a>
                    <a href="{{ route('admin.purchase-orders.index') }}" class="{{ request()->routeIs('admin.purchase-orders.*') ? 'active' : '' }}">
                        <i class="bi bi-clipboard-check"></i> {{ __('Purchase Orders') }}
                    </a>
                    <a href="{{ route('admin.reorder.index') }}" class="{{ request()->routeIs('admin.reorder.*') ? 'active' : '' }}">
                        <i class="bi bi-robot"></i> {{ __('Smart Reorder') }}
                    </a>
                    <a href="{{ route('admin.supplier-returns.index') }}" class="{{ request()->routeIs('admin.supplier-returns.*') ? 'active' : '' }}">
                        <i class="bi bi-box-arrow-left"></i> {{ __('Supplier Returns') }}
                    </a>
                    <a href="{{ route('admin.customers.index') }}" class="{{ request()->routeIs('admin.customers.*') ? 'active' : '' }}">
                        <i class="bi bi-people"></i> {{ __('Customers') }}
                    </a>
                    <a href="{{ route('returns.index') }}" class="{{ request()->routeIs('returns.*') ? 'active' : '' }}">
                        <i class="bi bi-arrow-return-left"></i> {{ __('Returns') }}
                    </a>

                    <div class="pos-nav-label">{{ __('Marketing') }}</div>
                    <a href="{{ route('admin.promotions.index') }}" class="{{ request()->routeIs('admin.promotions.*') ? 'active' : '' }}">
                        <i class="bi bi-megaphone"></i> {{ __('Promotion Manager') }}
                    </a>

                    <div class="pos-nav-label">{{ __('Analytics & AI') }}</div>
                    <a href="{{ route('admin.revenue.index') }}" class="{{ request()->routeIs('admin.revenue.*') ? 'active' : '' }}">
                        <i class="bi bi-cash-coin"></i> {{ __('Revenue') }}
                    </a>
                    <a href="{{ route('admin.reports.index') }}" class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
                        <i class="bi bi-bar-chart-line"></i> {{ __('Reports') }}
                    </a>
                    <a href="{{ route('admin.forecasts.index') }}" class="{{ request()->routeIs('admin.forecasts.*') ? 'active' : '' }}">
                        <i class="bi bi-graph-up-arrow"></i> {{ __('Forecasts') }}
                    </a>
                    <a href="{{ route('admin.ai-chat.index') }}" class="{{ request()->routeIs('admin.ai-chat.*') ? 'active' : '' }}">
                        <i class="bi bi-stars"></i> {{ __('AI Assistant') }}
                    </a>

                    <div class="pos-nav-label">{{ __('System') }}</div>
                    <a href="{{ route('admin.receipt-settings.edit') }}" class="{{ request()->routeIs('admin.receipt-settings.*') ? 'active' : '' }}">
                        <i class="bi bi-receipt-cutoff"></i> {{ __('Receipt Designer') }}
                    </a>
                    <a href="{{ route('admin.billing-settings.edit') }}" class="{{ request()->routeIs('admin.billing-settings.*') ? 'active' : '' }}">
                        <i class="bi bi-sliders"></i> {{ __('Billing Settings') }}
                    </a>
                    <a href="{{ route('admin.settings.edit') }}" class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                        <i class="bi bi-gear"></i> {{ __('Store Settings') }}
                    </a>
                    <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                        <i class="bi bi-people-fill"></i> {{ __('User Management') }}
                    </a>
                    <a href="{{ route('admin.activity-log.index') }}" class="{{ request()->routeIs('admin.activity-log.*') ? 'active' : '' }}">
                        <i class="bi bi-clock-history"></i> {{ __('Activity Log') }}
                    </a>
                </nav>

                <div class="pos-sidebar-footer">
                    <span>v1.0.0 &middot; {{ config('app.name', 'Foodcity') }} POS</span>
                </div>
            </aside>

            <!-- Main Content Shell -->
            <div class="pos-content-wrapper flex-grow-1 min-w-0 d-flex flex-column">
                @include('layouts.navigation', ['showSidebarToggle' => true])

                <main class="pos-main">
                    @isset($header)
                        <div class="mb-4">
                            {{ $header }}
                        </div>
                    @endisset

                    {{ $slot }}
                </main>
            </div>
        </div>

        <div class="toast-container position-fixed bottom-0 end-0 p-3" id="pos-toast-container" style="z-index:1080;"></div>

        <!-- JS Libraries -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
        <script src="{{ asset('js/pos.js') }}"></script>
    </body>
</html>
