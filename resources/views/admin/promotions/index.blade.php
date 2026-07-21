<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h2 class="h3 mb-0 fw-extrabold text-dark"><i class="bi bi-megaphone text-primary me-2"></i>{{ __('Promotion Manager') }}</h2>
                <div class="text-muted small">{{ __('Create, schedule, and manage promotional campaigns for the Customer Display.') }}</div>
            </div>
            <a href="{{ route('admin.promotions.create') }}" class="btn btn-primary btn-sm rounded-pill px-3"><i class="bi bi-plus-lg"></i> {{ __('New Promotion') }}</a>
        </div>
    </x-slot>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-2">
            <x-stat-card icon="bi-megaphone" tone="primary" :label="__('Total')" :value="$counts['total']" />
        </div>
        <div class="col-6 col-lg-2">
            <x-stat-card icon="bi-broadcast" tone="success" :label="__('Active')" :value="$counts['active']" />
        </div>
        <div class="col-6 col-lg-2">
            <x-stat-card icon="bi-clock-history" tone="info" :label="__('Scheduled')" :value="$counts['scheduled']" />
        </div>
        <div class="col-6 col-lg-2">
            <x-stat-card icon="bi-pause-circle" tone="warning" :label="__('Paused')" :value="$counts['paused']" />
        </div>
        <div class="col-6 col-lg-2">
            <x-stat-card icon="bi-x-circle" tone="danger" :label="__('Expired')" :value="$counts['expired']" />
        </div>
        <div class="col-6 col-lg-2">
            <x-stat-card icon="bi-star-fill" tone="warning" :label="__('Featured')" :value="$counts['featured']" />
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.promotions.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <x-input-label for="q" :value="__('Search')" />
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="q" name="q" class="form-control" value="{{ $search }}" placeholder="{{ __('Title, product, or status…') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <x-input-label for="filter" :value="__('Filter')" />
                    <select id="filter" name="filter" class="form-select">
                        <option value="all" @selected($filter === 'all')>{{ __('All') }}</option>
                        <option value="active" @selected($filter === 'active')>{{ __('Active') }}</option>
                        <option value="scheduled" @selected($filter === 'scheduled')>{{ __('Scheduled') }}</option>
                        <option value="paused" @selected($filter === 'paused')>{{ __('Paused') }}</option>
                        <option value="expired" @selected($filter === 'expired')>{{ __('Expired') }}</option>
                        <option value="featured" @selected($filter === 'featured')>{{ __('Featured') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <x-input-label for="sort" :value="__('Sort By')" />
                    <select id="sort" name="sort" class="form-select">
                        <option value="newest" @selected($sort === 'newest')>{{ __('Newest') }}</option>
                        <option value="oldest" @selected($sort === 'oldest')>{{ __('Oldest') }}</option>
                        <option value="priority" @selected($sort === 'priority')>{{ __('Priority') }}</option>
                        <option value="duration" @selected($sort === 'duration')>{{ __('Display Duration') }}</option>
                        <option value="start_date" @selected($sort === 'start_date')>{{ __('Start Date') }}</option>
                        <option value="end_date" @selected($sort === 'end_date')>{{ __('End Date') }}</option>
                        <option value="offer_price" @selected($sort === 'offer_price')>{{ __('Offer Price') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">{{ __('Apply') }}</button>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.promotions.bulk-action') }}" id="bulk-form">
        @csrf
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
                <span class="text-muted small fw-semibold">{{ __('Bulk Actions:') }}</span>
                <select name="bulk_action" class="form-select form-select-sm" style="width:auto;">
                    <option value="activate">{{ __('Activate') }}</option>
                    <option value="pause">{{ __('Pause') }}</option>
                    <option value="delete">{{ __('Delete') }}</option>
                </select>
                <button type="submit" class="btn btn-outline-primary btn-sm" data-confirm="{{ __('Apply this bulk action to the selected promotions?') }}" data-confirm-icon="warning">
                    {{ __('Apply to Selected') }}
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-bordered mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="select-all" class="form-check-input"></th>
                            <th>{{ __('Poster') }}</th>
                            <th>{{ __('Promotion') }}</th>
                            <th>{{ __('Product') }}</th>
                            <th class="text-end">{{ __('Current') }}</th>
                            <th class="text-end">{{ __('Offer') }}</th>
                            <th class="text-end">{{ __('Discount') }}</th>
                            <th>{{ __('Start') }}</th>
                            <th>{{ __('End') }}</th>
                            <th>{{ __('Duration') }}</th>
                            <th>{{ __('Priority') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Created By') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($promotions as $promotion)
                            @php
                                $statusMap = [
                                    'active' => ['🟢', 'bg-success-subtle text-success', __('Active')],
                                    'scheduled' => ['🟡', 'bg-info-subtle text-info', __('Scheduled')],
                                    'paused' => ['⏸', 'bg-secondary-subtle text-secondary', __('Paused')],
                                    'expired' => ['🔴', 'bg-danger-subtle text-danger', __('Expired')],
                                ];
                                [$statusIcon, $statusClass, $statusLabel] = $statusMap[$promotion->status] ?? $statusMap['paused'];
                                $priorityClass = ['high' => 'bg-danger-subtle text-danger', 'normal' => 'bg-primary-subtle text-primary', 'low' => 'bg-secondary-subtle text-secondary'][$promotion->priority] ?? 'bg-secondary-subtle text-secondary';
                            @endphp
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="{{ $promotion->id }}" class="form-check-input row-checkbox"></td>
                                <td>
                                    @if ($promotion->poster_path)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($promotion->poster_path) }}" class="rounded-2 border" style="width:56px;height:56px;object-fit:cover;" alt="">
                                    @else
                                        <div class="rounded-2 border bg-light d-flex align-items-center justify-content-center text-muted" style="width:56px;height:56px;"><i class="bi bi-image"></i></div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $promotion->title }}</div>
                                    @if ($promotion->is_featured)
                                        <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-star-fill"></i> {{ __('Featured') }}</span>
                                    @endif
                                </td>
                                <td>{{ $promotion->product->name ?? '—' }}</td>
                                <td class="text-end text-decoration-line-through text-muted">Rs {{ number_format($promotion->current_price, 2) }}</td>
                                <td class="text-end fw-bold text-dark">Rs {{ number_format($promotion->offer_price, 2) }}</td>
                                <td class="text-end"><span class="badge bg-success-subtle text-success">-{{ number_format($promotion->discount_percentage, 1) }}%</span></td>
                                <td class="small">{{ $promotion->start_date->format('d M Y') }}</td>
                                <td class="small">{{ $promotion->end_date->format('d M Y') }}</td>
                                <td class="small">{{ $promotion->display_duration }}s</td>
                                <td><span class="badge {{ $priorityClass }} text-capitalize">{{ $promotion->priority }}</span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge {{ $statusClass }}">{{ $statusIcon }} {{ $statusLabel }}</span>
                                        <form method="POST" action="{{ route('admin.promotions.toggle-status', $promotion) }}" data-confirm="{{ $promotion->status === 'paused' ? __('Activate this promotion?') : __('Pause this promotion?') }}" data-confirm-icon="question">
                                            @csrf
                                            <div class="form-check form-switch mb-0">
                                                <input type="checkbox" class="form-check-input" role="switch" onclick="return false;" @checked($promotion->status !== 'paused') title="{{ __('Toggle Active / Paused') }}">
                                            </div>
                                        </form>
                                    </div>
                                </td>
                                <td class="small text-muted">{{ $promotion->creator->name ?? '—' }}</td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="{{ route('admin.promotions.edit', $promotion) }}" class="btn btn-sm btn-outline-primary rounded-circle" style="width:32px;height:32px;padding:0;" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="{{ route('admin.promotions.destroy', $promotion) }}" data-confirm="{{ __('Delete this promotion? This cannot be undone.') }}" data-confirm-icon="warning">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" style="width:32px;height:32px;padding:0;" title="{{ __('Delete') }}"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="p-0"><x-empty-state icon="bi-megaphone" :title="__('No promotions yet')" :text="__('Create your first promotional campaign to get started.')" /></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    @if ($promotions->hasPages())
        <div class="mt-3">{{ $promotions->links() }}</div>
    @endif

    <script>
        document.getElementById('select-all')?.addEventListener('change', function () {
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
        });
    </script>
</x-admin-layout>
