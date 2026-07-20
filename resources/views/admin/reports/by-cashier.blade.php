<x-admin-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Sales by Cashier') }}</h2>
    </x-slot>

    @include('admin.reports._date-filter', ['routeName' => 'admin.reports.by-cashier'])

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Cashier') }}</th>
                        <th>{{ __('Transactions') }}</th>
                        <th>{{ __('Revenue') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->name }}</td>
                            <td>{{ $row->transactions }}</td>
                            <td>{{ number_format($row->revenue, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="p-0"><x-empty-state icon="bi-people" :title="__('No sales in this date range')" /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
