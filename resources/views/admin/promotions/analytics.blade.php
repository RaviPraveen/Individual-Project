<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0"><i class="bi bi-graph-up-arrow text-primary me-2"></i>{{ __('Promotion Analytics') }}</h2>
                <div class="text-muted small">{{ __('Display counts, sales driven, and revenue per promotion.') }}</div>
            </div>
            <a href="{{ route('admin.promotions.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-arrow-left"></i> {{ __('Back to Promotion Manager') }}</a>
        </div>
    </x-slot>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-4">
            <x-stat-card icon="bi-eye" tone="primary" :label="__('Total Displays / Views')" :value="number_format($totals['display_count'])" />
        </div>
        <div class="col-6 col-lg-4">
            <x-stat-card icon="bi-cart-check" tone="success" :label="__('Units Sold During Promotions')" :value="number_format($totals['units_sold'])" />
        </div>
        <div class="col-6 col-lg-4">
            <x-stat-card icon="bi-cash-stack" tone="warning" :label="__('Promotion-Driven Revenue')" :value="'Rs '.number_format($totals['revenue'], 2)" />
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold text-dark border-bottom py-3">
            <i class="bi bi-trophy me-1.5 text-gold"></i> {{ __('Best Performing Promotions') }}
        </div>
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Promotion') }}</th>
                        <th>{{ __('Product') }}</th>
                        <th class="text-end">{{ __('Revenue') }}</th>
                        <th class="text-end">{{ __('Units Sold') }}</th>
                        <th class="text-end">{{ __('Conversion %') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($best as $row)
                        <tr>
                            <td class="fw-semibold text-dark">{{ $row['promotion']->title }}</td>
                            <td>{{ $row['promotion']->product->name ?? '—' }}</td>
                            <td class="text-end">Rs {{ number_format($row['metrics']['revenue'], 2) }}</td>
                            <td class="text-end">{{ $row['metrics']['units_sold'] }}</td>
                            <td class="text-end">{{ number_format($row['metrics']['conversion_rate'], 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-0"><x-empty-state icon="bi-trophy" :title="__('No promotion sales data yet')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold text-dark border-bottom py-3">
            <i class="bi bi-list-ul me-1.5 text-primary"></i> {{ __('All Promotions') }}
        </div>
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Promotion') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Views / Displays') }}</th>
                        <th class="text-end">{{ __('Est. Reach') }}</th>
                        <th class="text-end">{{ __('Units Sold') }}</th>
                        <th class="text-end">{{ __('Revenue') }}</th>
                        <th class="text-end">{{ __('Conversion %') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="fw-semibold text-dark">{{ $row['promotion']->title }}</td>
                            <td><span class="badge bg-secondary-subtle text-secondary text-capitalize">{{ $row['promotion']->status }}</span></td>
                            <td class="text-end">{{ number_format($row['metrics']['display_count']) }}</td>
                            <td class="text-end">{{ number_format($row['metrics']['estimated_reach']) }}</td>
                            <td class="text-end">{{ $row['metrics']['units_sold'] }}</td>
                            <td class="text-end">Rs {{ number_format($row['metrics']['revenue'], 2) }}</td>
                            <td class="text-end">{{ number_format($row['metrics']['conversion_rate'], 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-0"><x-empty-state icon="bi-megaphone" :title="__('No promotions yet')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
