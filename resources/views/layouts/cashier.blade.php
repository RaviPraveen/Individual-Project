<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&display=swap" rel="stylesheet">
        <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    </head>
    <body>
        <div id="pos-flash-data" data-success="{{ session('success') }}" data-error="{{ session('error') }}" hidden></div>

        <div class="pos-shell d-flex">
            <aside class="pos-sidebar">
                <a href="{{ route('cashier.dashboard') }}" class="brand">
                    <span class="brand-mark">🛒</span>
                    <span>{{ config('app.name', 'Laravel') }}</span>
                </a>

                <nav class="pos-nav">
                    <a href="{{ route('cashier.dashboard') }}" class="{{ request()->routeIs('cashier.dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2"></i> {{ __('Dashboard') }}
                    </a>
                    <a href="{{ route('cashier.billing.index') }}" class="{{ request()->routeIs('cashier.billing.*') ? 'active' : '' }}">
                        <i class="bi bi-cart-check"></i> {{ __('Billing') }}
                    </a>
                    <a href="{{ route('returns.index') }}" class="{{ request()->routeIs('returns.*') ? 'active' : '' }}">
                        <i class="bi bi-arrow-return-left"></i> {{ __('Returns') }}
                    </a>
                    <a href="{{ route('cashier.ai-chat.index') }}" class="{{ request()->routeIs('cashier.ai-chat.*') ? 'active' : '' }}">
                        <i class="bi bi-robot"></i> {{ __('AI Assistant') }}
                    </a>
                </nav>
            </aside>

            <div class="flex-grow-1 min-w-0">
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

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="{{ asset('js/pos.js') }}"></script>
    </body>
</html>
