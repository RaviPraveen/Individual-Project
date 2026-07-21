<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Foodcity POS') }} — 2026 Commercial SaaS POS</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link href="{{ asset('css/app.css') }}" rel="stylesheet">
        <style>
            body.pos-welcome {
                background: radial-gradient(circle at 50% 0%, #EEF2FF 0%, #F8FAFC 75%);
                min-height: 100vh;
            }
        </style>
    </head>
    <body class="pos-welcome d-flex align-items-center justify-content-center min-vh-100 p-4">
        <div class="text-center pos-animate-in max-w-xl mx-auto">
            <div class="d-inline-flex align-items-center justify-content-center mb-4 shadow-lg" style="width:80px;height:80px;background:var(--pos-brand-gradient);border-radius:24px;font-size:2.5rem;color:#FFF;">🛒</div>
            <h1 class="mb-2 fw-extrabold text-dark display-5">{{ config('app.name', 'Foodcity POS') }}</h1>
            <p class="text-muted fs-5 mb-4 max-w-md mx-auto">{{ __('Commercial SaaS Point of Sale & Retail Management System with Artificial Intelligence') }}</p>

            <div class="d-flex justify-content-center gap-3">
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-lg rounded-pill px-5 py-3 fw-bold shadow-md">
                        <i class="bi bi-speedometer2 me-2"></i>{{ __('Go to Station Dashboard') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-lg rounded-pill px-5 py-3 fw-bold shadow-md">
                        <i class="bi bi-box-arrow-in-right me-2"></i>{{ __('Log In to POS Register') }}
                    </a>
                @endauth
            </div>

            <div class="mt-5 text-muted small fw-medium">
                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill me-2">⚡ Ultra-Fast Billing</span>
                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill me-2">✨ AI Copilot</span>
                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">⭐ Star Points Loyalty</span>
            </div>
        </div>
    </body>
</html>
