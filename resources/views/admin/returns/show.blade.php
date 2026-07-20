<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ __('Return') }} {{ $saleReturn->return_no }}</h2>
            <a href="{{ route('returns.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back to Returns') }}</a>
        </div>
    </x-slot>

    <div class="card">
        <div class="card-body">
            @include('returns._receipt_body', ['saleReturn' => $saleReturn, 'settings' => $settings])
        </div>
    </div>
</x-admin-layout>
