@php $user = $user ?? null; @endphp

<div class="mb-3">
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" name="name" type="text" :value="old('name', $user?->name)" required autofocus />
    <x-input-error :messages="$errors->get('name')" />
</div>

<div class="mb-3">
    <x-input-label for="email" :value="__('Email')" />
    <x-text-input id="email" name="email" type="email" :value="old('email', $user?->email)" required autocomplete="off" />
    <x-input-error :messages="$errors->get('email')" />
</div>

<div class="mb-3">
    <x-input-label for="role" :value="__('Role')" />
    <select id="role" name="role" class="form-select" required>
        <option value="cashier" @selected(old('role', $user?->role ?? 'cashier') === 'cashier')>{{ __('Cashier') }}</option>
        <option value="admin"   @selected(old('role', $user?->role) === 'admin')>{{ __('Admin') }}</option>
    </select>
    <x-input-error :messages="$errors->get('role')" />
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <x-input-label for="password" :value="$user ? __('New Password (leave blank to keep existing)') : __('Password')" />
        <x-text-input id="password" name="password" type="password" :required="! $user" autocomplete="new-password" />
        <x-input-error :messages="$errors->get('password')" />
    </div>
    <div class="col-md-6 mb-3">
        <x-input-label for="password_confirmation" :value="$user ? __('Confirm New Password') : __('Confirm Password')" />
        <x-text-input id="password_confirmation" name="password_confirmation" type="password" :required="! $user" autocomplete="new-password" />
    </div>
</div>

@if ($user)
    <div class="mb-3 form-check">
        <input id="is_active" type="checkbox" class="form-check-input" name="is_active" value="1"
            @checked(old('is_active', $user->is_active))>
        <label for="is_active" class="form-check-label">{{ __('Active') }}</label>
    </div>
@endif
