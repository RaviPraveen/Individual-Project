@php
    $layout = auth()->user()->isAdmin() ? 'x-admin-layout' : 'x-cashier-layout';
@endphp

<x-dynamic-component :component="auth()->user()->isAdmin() ? 'admin-layout' : 'cashier-layout'">
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('My Profile') }}</h2>
    </x-slot>

    {{-- Force password reset notice --}}
    @if (session('force_reset_notice'))
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div>{{ session('force_reset_notice') }}</div>
        </div>
    @endif

    <div class="d-flex flex-column gap-4" style="max-width: 40rem;">
        <div class="card">
            <div class="card-body">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    </div>
</x-dynamic-component>
