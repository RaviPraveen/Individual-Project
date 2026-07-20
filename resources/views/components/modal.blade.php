@props([
    'name',
    'show' => false,
])

<div class="modal fade @if ($show) show d-block @endif" id="{{ $name }}" tabindex="-1" @if ($show) style="background-color: rgba(0,0,0,0.5);" @endif>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            {{ $slot }}
        </div>
    </div>
</div>
