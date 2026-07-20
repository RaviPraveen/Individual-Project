@props(['icon' => 'bi-graph-up', 'label', 'value', 'tone' => 'success', 'trend' => null])

<div {{ $attributes->merge(['class' => 'card h-100 pos-stat-card']) }}>
    <div class="card-body d-flex align-items-center gap-3">
        <div class="icon-badge bg-{{ $tone }}-subtle text-{{ $tone }}">
            <i class="bi {{ $icon }}"></i>
        </div>
        <div class="min-w-0">
            <div class="label">{{ $label }}</div>
            <div class="value num-tabular">{{ $value }}</div>
            @if ($trend)
                <div class="trend {{ str_starts_with($trend, '-') ? 'down' : 'up' }}">
                    <i class="bi {{ str_starts_with($trend, '-') ? 'bi-arrow-down-right' : 'bi-arrow-up-right' }}"></i> {{ $trend }}
                </div>
            @endif
        </div>
    </div>
</div>
