<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ __('Dead-Stock / Slow-Moving Report') }}</h2>
            <a href="{{ route('admin.reports.dead-stock', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-outline-success btn-sm">{{ __('Export CSV') }}</a>
        </div>
    </x-slot>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.reports.dead-stock') }}" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <x-input-label for="days" :value="__('Lookback (days)')" />
                    <x-text-input id="days" name="days" type="number" min="1" value="{{ $days }}" />
                </div>
                <div class="col-md-2">
                    <x-input-label for="max_qty_sold" :value="__('Max Qty Sold')" />
                    <x-text-input id="max_qty_sold" name="max_qty_sold" type="number" min="0" value="{{ $maxQtySold }}" />
                </div>
                <div class="col-md-2">
                    <x-input-label for="category_id" :value="__('Category')" />
                    <select id="category_id" name="category_id" class="form-select">
                        <option value="">{{ __('All Categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($categoryId == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <x-input-label for="supplier_id" :value="__('Supplier')" />
                    <select id="supplier_id" name="supplier_id" class="form-select">
                        <option value="">{{ __('All Suppliers') }}</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected($supplierId == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <x-input-label for="sort" :value="__('Sort By')" />
                    <select id="sort" name="sort" class="form-select">
                        <option value="slowest" @selected($sort === 'slowest')>{{ __('Slowest Selling') }}</option>
                        <option value="value" @selected($sort === 'value')>{{ __('Highest Stock Value') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">{{ __('Apply') }}</button>
                </div>
            </form>
        </div>
    </div>

    @if ($rows->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-stars me-1 text-gold"></i> {{ __('AI Return-to-Supplier Insight') }}</div>
            <div class="card-body">
                @if ($narrative)
                    <p class="mb-0">{{ $narrative }}</p>
                @else
                    <div class="alert alert-secondary mb-0">
                        {{ __('AI summary unavailable (AI service not configured or unreachable). Showing figures below instead.') }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('SKU') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Stock Qty') }}</th>
                        <th>{{ __('Qty Sold') }}</th>
                        <th>{{ __('Velocity/Week') }}</th>
                        <th>{{ __('Stock Value') }}</th>
                        <th>{{ __('Supplier') }}</th>
                        <th>{{ __('Days to Expiry') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="{{ $row['is_near_expiry'] ? 'table-danger' : 'table-warning' }}">
                            <td>
                                {{ $row['product']->name }}
                                @if ($row['is_near_expiry'])
                                    <span class="badge bg-danger ms-1">{{ __('Near Expiry') }}</span>
                                @endif
                            </td>
                            <td>{{ $row['product']->sku }}</td>
                            <td>{{ $row['product']->category?->name }}</td>
                            <td>{{ $row['product']->stock_qty }}</td>
                            <td>{{ $row['qty_sold'] }}</td>
                            <td>{{ $row['velocity_per_week'] }}</td>
                            <td>{{ number_format($row['stock_value'], 2) }}</td>
                            <td>{{ $row['supplier']['name'] ?? '—' }}</td>
                            <td>{{ $row['days_to_expiry'] ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.supplier-returns.create', [
                                    'product_id' => $row['product']->id,
                                    'supplier_id' => $row['supplier']['id'] ?? null,
                                    'quantity' => $row['product']->stock_qty,
                                    'reason' => $row['is_near_expiry'] ? 'near_expiry' : 'not_selling',
                                ]) }}" class="btn btn-outline-danger btn-sm">{{ __('Return to Supplier') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="p-0"><x-empty-state icon="bi-graph-up" :title="__('No slow-moving stock found')" :text="__('Every product is selling above the configured threshold for this period.')" /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
