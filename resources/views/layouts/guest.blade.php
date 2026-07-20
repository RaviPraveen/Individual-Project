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
        <style>
            body.pos-guest {
                background: radial-gradient(circle at 15% 10%, var(--pos-brand-light), var(--pos-bg) 55%);
                min-height: 100vh;
            }
            .pos-guest-card { width: 100%; max-width: 26rem; border-top: 4px solid var(--pos-brand); }
        </style>
    </head>
    <body class="pos-guest d-flex flex-column align-items-center justify-content-center py-5">
        <div class="mb-4 text-center">
            <a href="/" class="text-decoration-none d-inline-flex align-items-center gap-2 fs-4 fw-bold" style="color: var(--pos-ink);">
                <span class="d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;background:var(--pos-brand);border-radius:.7rem;font-size:1.2rem;">🛒</span>
                {{ config('app.name', 'Laravel') }}
            </a>
        </div>

        <div class="card pos-guest-card pos-animate-in">
            <div class="card-body p-4">
                {{ $slot }}
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="{{ asset('js/pos.js') }}"></script>
    </body>
</html>
