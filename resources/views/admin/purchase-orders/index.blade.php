<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ __('Purchase Orders') }}</h2>
            <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary btn-sm">{{ __('New Purchase Order') }}</a>
        </div>
    </x-slot>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Order Date') }}</th>
                        <th>{{ __('Supplier') }}</th>
                        <th>{{ __('Total') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseOrders as $po)
                        <tr>
                            <td>{{ $po->order_date->format('Y-m-d') }}</td>
                            <td>{{ $po->supplier->name }}</td>
                            <td>{{ number_format($po->total_amount, 2) }}</td>
                            <td>
                                <span class="badge {{ match($po->status) {
                                    'pending' => 'bg-warning text-dark',
                                    'received' => 'bg-success',
                                    'cancelled' => 'bg-secondary',
                                } }}">
                                    {{ ucfirst($po->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.purchase-orders.show', $po) }}" class="btn btn-outline-secondary btn-sm">{{ __('View') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-0">
                                <x-empty-state icon="bi-clipboard-check" :title="__('No purchase orders yet')" :text="__('Create one to start restocking from a supplier.')">
                                    <x-slot name="action">
                                        <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary btn-sm">{{ __('New Purchase Order') }}</a>
                                    </x-slot>
                                </x-empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $purchaseOrders->links() }}
    </div>
</x-admin-layout>
