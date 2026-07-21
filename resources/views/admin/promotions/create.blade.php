<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0"><i class="bi bi-megaphone text-primary me-2"></i>{{ __('Create Promotion') }}</h2>
                <div class="text-muted small">{{ __('Set up a new promotional campaign for the Customer Display and dashboard.') }}</div>
            </div>
            <a href="{{ route('admin.promotions.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-arrow-left"></i> {{ __('Back to List') }}</a>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('admin.promotions.store') }}" enctype="multipart/form-data">
        @csrf

        @include('admin.promotions._form')

        <div class="mt-3">
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check2 me-1"></i>{{ __('Create Promotion') }}</button>
            <a href="{{ route('admin.promotions.index') }}" class="btn btn-outline-secondary px-4">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-admin-layout>
