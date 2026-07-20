<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ __('Purchase Order #').$purchaseOrder->id }}</h2>
            <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
        </div>
    </x-slot>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="text-muted small">{{ __('Supplier') }}</div>
                    <div>{{ $purchaseOrder->supplier->name }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">{{ __('Order Date') }}</div>
                    <div>{{ $purchaseOrder->order_date->format('Y-m-d') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">{{ __('Created By') }}</div>
                    <div>{{ $purchaseOrder->creator->name }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">{{ __('Status') }}</div>
                    <span class="badge {{ match($purchaseOrder->status) {
                        'pending' => 'bg-warning text-dark',
                        'received' => 'bg-success',
                        'cancelled' => 'bg-secondary',
                    } }}">
                        {{ ucfirst($purchaseOrder->status) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Quantity') }}</th>
                        <th>{{ __('Unit Cost') }}</th>
                        <th>{{ __('Line Total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchaseOrder->items as $item)
                        <tr>
                            <td>{{ $item->product->name }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ number_format($item->unit_cost, 2) }}</td>
                            <td>{{ number_format($item->quantity * $item->unit_cost, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">{{ __('Total') }}</th>
                        <th>{{ number_format($purchaseOrder->total_amount, 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @if ($purchaseOrder->status === 'pending')
        <div class="mt-3 d-flex gap-2">
            <form method="POST" action="{{ route('admin.purchase-orders.mark-received', $purchaseOrder) }}" onsubmit="return confirm('{{ __('Mark received and increase stock for all line items?') }}');">
                @csrf
                <button type="submit" class="btn btn-success">{{ __('Mark Received') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.purchase-orders.cancel', $purchaseOrder) }}" onsubmit="return confirm('{{ __('Cancel this purchase order?') }}');">
                @csrf
                <button type="submit" class="btn btn-outline-danger">{{ __('Cancel Order') }}</button>
            </form>
        </div>
    @endif
</x-admin-layout>
