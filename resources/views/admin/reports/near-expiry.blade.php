<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ __('Near-Expiry Report') }}</h2>
            <a href="{{ route('admin.reports.near-expiry', ['days' => $days, 'export' => 'csv']) }}" class="btn btn-outline-success btn-sm">{{ __('Export CSV') }}</a>
        </div>
    </x-slot>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.reports.near-expiry') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <x-input-label for="days" :value="__('Within how many days')" />
                    <x-text-input id="days" name="days" type="number" min="1" value="{{ $days }}" />
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary">{{ __('Apply') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('SKU') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Stock') }}</th>
                        <th>{{ __('Expiry Date') }}</th>
                        <th>{{ __('Days Left') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php $daysLeft = (int) now()->startOfDay()->diffInDays($row->expiry_date, false); @endphp
                        <tr class="{{ $daysLeft <= 2 ? 'table-danger' : 'table-warning' }}">
                            <td>{{ $row->name }}</td>
                            <td>{{ $row->sku }}</td>
                            <td>{{ $row->category?->name }}</td>
                            <td>{{ $row->stock_qty }}</td>
                            <td>{{ $row->expiry_date->format('Y-m-d') }}</td>
                            <td>{{ $daysLeft }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-0"><x-empty-state icon="bi-check-circle" :title="__('Nothing expiring soon')" :text="__('No active products expire within the selected window.')" /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
