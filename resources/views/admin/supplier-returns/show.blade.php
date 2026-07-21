<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ __('Supplier Return #').$supplierReturn->id }}</h2>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.supplier-returns.pdf', $supplierReturn) }}" target="_blank" class="btn btn-outline-secondary btn-sm">{{ __('Print / PDF') }}</a>
                <a href="{{ route('admin.supplier-returns.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="text-muted small">{{ __('Supplier') }}</div>
                    <div>{{ $supplierReturn->supplier->name }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">{{ __('Return Date') }}</div>
                    <div>{{ $supplierReturn->return_date->format('Y-m-d') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">{{ __('Created By') }}</div>
                    <div>{{ $supplierReturn->creator->name }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">{{ __('Status') }}</div>
                    <span class="badge {{ match($supplierReturn->status) {
                        'pending' => 'bg-warning text-dark',
                        'completed' => 'bg-success',
                        'cancelled' => 'bg-secondary',
                    } }}">
                        {{ ucfirst($supplierReturn->status) }}
                    </span>
                </div>
            </div>
            @if ($supplierReturn->credit_note_value > 0 || $supplierReturn->resolution !== 'none')
                <div class="row mt-3">
                    <div class="col-md-3">
                        <div class="text-muted small">{{ __('Resolution') }}</div>
                        <div>{{ ucfirst($supplierReturn->resolution) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">{{ __('Credit Note Value') }}</div>
                        <div>{{ number_format($supplierReturn->credit_note_value, 2) }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Quantity') }}</th>
                        <th>{{ __('Reason') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($supplierReturn->items as $item)
                        <tr>
                            <td>{{ $item->product->name }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ __(ucfirst(str_replace('_', ' ', $item->reason))) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($supplierReturn->status === 'pending')
        <div class="mt-3 d-flex gap-2">
            <form method="POST" action="{{ route('admin.supplier-returns.complete', $supplierReturn) }}" onsubmit="return confirm('{{ __('Complete this return and decrease stock for all line items?') }}');">
                @csrf
                <button type="submit" class="btn btn-success">{{ __('Complete Return') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.supplier-returns.cancel', $supplierReturn) }}" onsubmit="return confirm('{{ __('Cancel this supplier return?') }}');">
                @csrf
                <button type="submit" class="btn btn-outline-danger">{{ __('Cancel Return') }}</button>
            </form>
        </div>
    @endif
</x-admin-layout>
