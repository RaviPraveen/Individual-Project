<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Foodcity POS') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link href="{{ asset('css/app.css') }}" rel="stylesheet">
        <style>
            body.pos-guest {
                background: radial-gradient(circle at 50% 0%, #EEF2FF 0%, #F8FAFC 75%);
                min-height: 100vh;
            }
            .pos-guest-card {
                width: 100%;
                max-width: 28rem;
                border-top: 5px solid var(--pos-brand);
                border-radius: var(--pos-radius-lg);
                box-shadow: var(--pos-shadow-lg);
            }
        </style>
    </head>
    <body class="pos-guest d-flex flex-column align-items-center justify-content-center py-5">
        <div class="mb-4 text-center">
            <a href="/" class="text-decoration-none d-inline-flex align-items-center gap-2 fs-3 fw-bold" style="color: var(--pos-ink);">
                <span class="d-inline-flex align-items-center justify-content-center shadow-md" style="width:48px;height:48px;background:var(--pos-brand-gradient);border-radius:14px;font-size:1.4rem;color:#FFF;">🛒</span>
                {{ config('app.name', 'Foodcity POS') }}
            </a>
            <div class="text-muted small mt-1 fw-medium">{{ __('2026 Commercial SaaS POS') }}</div>
        </div>

        <div class="card pos-guest-card pos-animate-in">
            <div class="card-body p-4 p-md-5">
                {{ $slot }}
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="{{ asset('js/pos.js') }}"></script>
    </body>
</html>
