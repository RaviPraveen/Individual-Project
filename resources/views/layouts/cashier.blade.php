<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Foodcity POS') }} — Cashier</title>

        <!-- Fonts & Icons -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

        <!-- Centralized SaaS POS Design System -->
        <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    </head>
    <body>
        <div id="pos-flash-data" data-success="{{ session('success') }}" data-error="{{ session('error') }}" hidden></div>

        <div class="pos-shell">
            <!-- Fixed Premium Light Blue Cashier Sidebar -->
            <aside class="pos-sidebar">
                <a href="{{ route('cashier.dashboard') }}" class="brand">
                    <span class="brand-mark">🛒</span>
                    <div class="d-flex flex-column">
                        <span class="lh-1">{{ config('app.name', 'Foodcity') }}</span>
                        <span class="fw-semibold text-uppercase mt-1" style="font-size: 0.65rem; letter-spacing: 0.1em; color: #3B82F6;">Cashier POS</span>
                    </div>
                </a>

                <nav class="pos-nav">
                    <div class="pos-nav-label">{{ __('REGISTER') }}</div>
                    <a href="{{ route('cashier.dashboard') }}" class="{{ request()->routeIs('cashier.dashboard') ? 'active' : '' }}">
                        <i class="bi bi-grid-1x2-fill"></i> {{ __('Dashboard') }}
                    </a>
                    <a href="{{ route('cashier.billing.index') }}" class="{{ request()->routeIs('cashier.billing.*') ? 'active' : '' }}">
                        <i class="bi bi-cart-check-fill"></i> {{ __('POS Billing') }}
                    </a>
                    <a href="{{ route('returns.index') }}" class="{{ request()->routeIs('returns.*') ? 'active' : '' }}">
                        <i class="bi bi-arrow-return-left"></i> {{ __('Returns') }}
                    </a>

                    <div class="pos-nav-label">{{ __('OPERATIONS') }}</div>
                    <a href="{{ route('cashier.ai-chat.index') }}" class="{{ request()->routeIs('cashier.ai-chat.*') ? 'active' : '' }}">
                        <i class="bi bi-stars"></i> {{ __('AI Assistant') }}
                    </a>
                </nav>

                <div class="pos-sidebar-footer">
                    <span>v1.0.0 &middot; {{ config('app.name', 'Foodcity') }} POS</span>
                </div>
            </aside>

            <!-- Main Shell -->
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
        <script src="{{ asset('js/pos.js') }}"></script>
    </body>
</html>
