@php $customer = $customer ?? null; @endphp

<div class="mb-3">
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" name="name" type="text" :value="old('name', $customer?->name)" required autofocus />
    <x-input-error :messages="$errors->get('name')" />
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <x-input-label for="phone" :value="__('Phone')" />
        <x-text-input id="phone" name="phone" type="text" :value="old('phone', $customer?->phone)" />
        <x-input-error :messages="$errors->get('phone')" />
    </div>
    <div class="col-md-6 mb-3">
        <x-input-label for="email" :value="__('Email')" />
        <x-text-input id="email" name="email" type="email" :value="old('email', $customer?->email)" />
        <x-input-error :messages="$errors->get('email')" />
    </div>
</div>

<div class="mb-3">
    <x-input-label for="address" :value="__('Address')" />
    <textarea id="address" name="address" class="form-control" rows="3">{{ old('address', $customer?->address) }}</textarea>
    <x-input-error :messages="$errors->get('address')" />
</div>
