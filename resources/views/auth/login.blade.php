<x-guest-layout>
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center mb-3 shadow-md" style="width:64px;height:64px;background:var(--pos-brand-gradient);border-radius:18px;font-size:2rem;color:#FFF;">🛒</div>
        <h1 class="h4 fw-extrabold text-dark mb-1">{{ config('app.name', 'Foodcity POS') }}</h1>
        <p class="text-muted small mb-0">{{ __('Commercial SaaS Point of Sale Terminal') }}</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <x-input-label for="email" :value="__('Email Address')" />
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                <x-text-input id="email" class="border-start-0 ps-0" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="name@store.com" />
            </div>
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="mb-3">
            <x-input-label for="password" :value="__('Password')" />
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                <x-text-input id="password" class="border-start-0 ps-0" type="password" name="password" required autocomplete="current-password" placeholder="••••••••" />
            </div>
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="mb-4 form-check form-switch p-2 bg-light rounded-3 border ps-5">
            <input id="remember_me" type="checkbox" class="form-check-input" name="remember" style="margin-left:-2.5em;">
            <label for="remember_me" class="form-check-label fw-semibold text-dark small">{{ __('Remember this station') }}</label>
        </div>

        <x-primary-button class="w-100 py-3 fw-bold shadow-md rounded-3 fs-6">
            <i class="bi bi-box-arrow-in-right me-1.5"></i> {{ __('Log In to POS Register') }}
        </x-primary-button>
    </form>
</x-guest-layout>
