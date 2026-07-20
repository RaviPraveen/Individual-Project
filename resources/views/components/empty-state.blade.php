@props(['icon' => 'bi-inbox', 'title', 'text' => null])

<div {{ $attributes->merge(['class' => 'pos-empty']) }}>
    <div class="pos-empty-icon"><i class="bi {{ $icon }}"></i></div>
    <div class="pos-empty-title">{{ $title }}</div>
    @if ($text)
        <div class="pos-empty-text">{{ $text }}</div>
    @endif
    @isset($action)
        <div class="mt-3">{{ $action }}</div>
    @endisset
</div>
