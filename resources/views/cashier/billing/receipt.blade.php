<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Receipt') }} {{ $sale->invoice_no }}</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&display=swap" rel="stylesheet">
        <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
        <style>
            @media print {
                .no-print { display: none !important; }
                body { padding: 0; }
            }
        </style>
    </head>
    <body>
        <div class="container py-4 d-flex flex-column align-items-center">
            <div class="no-print d-flex justify-content-between mb-3" style="width: 100%; max-width: 26rem;">
                <a href="{{ route('cashier.billing.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('New Sale') }}</a>
                <div class="d-flex gap-2">
                    <a href="{{ route('cashier.billing.receipt.pdf', $sale) }}" class="btn btn-outline-secondary btn-sm" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> {{ __('PDF') }}
                    </a>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                        <i class="bi bi-printer"></i> {{ __('Print') }}
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    @include('receipts._body', ['settings' => $settings, 'data' => $data, 'forPdf' => false])
                </div>
            </div>
        </div>
    </body>
</html>
