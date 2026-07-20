@props(['disabled' => false])

@php
$name = $attributes->get('name');
$hasError = $name && $errors->has($name);
@endphp

<input @disabled($disabled) {{ $attributes->merge(['class' => 'form-control' . ($hasError ? ' is-invalid' : '')]) }}>
