<x-cashier-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Returns & Refunds') }}</h2>
    </x-slot>

    @include('returns._content', ['returns' => $returns, 'stats' => $stats])
</x-cashier-layout>
