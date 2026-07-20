<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ __('Low Stock Report') }}</h2>
            <a href="{{ route('admin.reports.low-stock', ['export' => 'csv']) }}" class="btn btn-outline-success btn-sm">{{ __('Export CSV') }}</a>
        </div>
    </x-slot>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('SKU') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Stock') }}</th>
                        <th>{{ __('Reorder Level') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="table-warning">
                            <td>{{ $row->name }}</td>
                            <td>{{ $row->sku }}</td>
                            <td>{{ $row->category?->name }}</td>
                            <td>{{ $row->stock_qty }}</td>
                            <td>{{ $row->reorder_level }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-0"><x-empty-state icon="bi-check-circle" :title="__('All stock levels are healthy')" :text="__('No products are currently at or below their reorder level.')" /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
