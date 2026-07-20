<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }}</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&display=swap" rel="stylesheet">
        <link href="{{ asset('css/app.css') }}" rel="stylesheet">
        <style>
            body.pos-guest { background: radial-gradient(circle at 15% 10%, var(--pos-brand-light), var(--pos-bg) 55%); }
        </style>
    </head>
    <body class="pos-guest d-flex align-items-center justify-content-center min-vh-100">
        <div class="text-center pos-animate-in">
            <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;background:var(--pos-brand);border-radius:1rem;font-size:2rem;">🛒</div>
            <h1 class="mb-2 fw-bold">{{ config('app.name', 'Laravel') }}</h1>
            <p class="text-muted mb-4">{{ __('Shop Billing System with Artificial Intelligence') }}</p>

            @auth
                <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-lg px-4">{{ __('Go to Dashboard') }}</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary btn-lg px-4">{{ __('Log in') }}</a>
            @endauth
        </div>
    </body>
</html>
