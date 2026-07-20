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

        @include('layouts.navigation')

        @isset($header)
            <header class="border-bottom" style="background: var(--pos-surface);">
                <div class="container py-3">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <main class="container py-4">
            {{ $slot }}
        </main>

        <div class="toast-container position-fixed bottom-0 end-0 p-3" id="pos-toast-container" style="z-index:1080;"></div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="{{ asset('js/pos.js') }}"></script>
    </body>
</html>
