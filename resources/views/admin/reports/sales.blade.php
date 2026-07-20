<x-admin-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Sales Summary') }}</h2>
    </x-slot>

    @include('admin.reports._date-filter', ['routeName' => 'admin.reports.sales'])

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Period') }}</th>
                        <th>{{ __('Transactions') }}</th>
                        <th>{{ __('Subtotal') }}</th>
                        <th>{{ __('Discount') }}</th>
                        <th>{{ __('Tax') }}</th>
                        <th>{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->period }}</td>
                            <td>{{ $row->transactions }}</td>
                            <td>{{ number_format($row->subtotal, 2) }}</td>
                            <td>{{ number_format($row->discount, 2) }}</td>
                            <td>{{ number_format($row->tax, 2) }}</td>
                            <td>{{ number_format($row->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-0"><x-empty-state icon="bi-bar-chart-line" :title="__('No sales in this date range')" :text="__('Try widening the start and end dates above.')" /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
