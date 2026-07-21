@props(['icon' => 'bi-graph-up', 'label', 'value', 'tone' => 'primary', 'trend' => null])

@php
    $bgMap = [
        'primary' => 'background: #EEF2FF; color: #4F46E5;',
        'success' => 'background: #ECFDF5; color: #059669;',
        'warning' => 'background: #FFFBEB; color: #D97706;',
        'danger' => 'background: #FEF2F2; color: #DC2626;',
        'info' => 'background: #EFF6FF; color: #2563EB;',
    ];
    $badgeStyle = $bgMap[$tone] ?? $bgMap['primary'];
@endphp

<div {{ $attributes->merge(['class' => 'card h-100 pos-stat-card border-0 shadow-sm']) }}>
    <div class="card-body p-3.5 d-flex align-items-center gap-3">
        <div class="icon-badge rounded-3 p-3 flex-shrink-0" style="{{ $badgeStyle }}">
            <i class="bi {{ $icon }} fs-4"></i>
        </div>
        <div class="min-w-0 flex-grow-1">
            <div class="label text-uppercase text-muted fw-bold small" style="letter-spacing: 0.05em; font-size: 0.7rem;">{{ $label }}</div>
            <div class="value num-tabular fw-extrabold text-dark h3 mb-0">{{ $value }}</div>
            @if ($trend)
                <div class="trend mt-1 {{ str_starts_with($trend, '-') ? 'down' : 'up' }}">
                    <i class="bi {{ str_starts_with($trend, '-') ? 'bi-arrow-down-right' : 'bi-arrow-up-right' }}"></i> {{ $trend }}
                </div>
            @endif
        </div>
    </div>
</div>
