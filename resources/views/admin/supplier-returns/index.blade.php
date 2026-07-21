<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ __('Supplier Returns') }}</h2>
            <a href="{{ route('admin.supplier-returns.create') }}" class="btn btn-primary btn-sm">{{ __('New Supplier Return') }}</a>
        </div>
    </x-slot>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Return Date') }}</th>
                        <th>{{ __('Supplier') }}</th>
                        <th>{{ __('Items') }}</th>
                        <th>{{ __('Credit Note Value') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($supplierReturns as $supplierReturn)
                        <tr>
                            <td>{{ $supplierReturn->return_date->format('Y-m-d') }}</td>
                            <td>{{ $supplierReturn->supplier->name }}</td>
                            <td>{{ $supplierReturn->items_count }}</td>
                            <td>{{ number_format($supplierReturn->credit_note_value, 2) }}</td>
                            <td>
                                <span class="badge {{ match($supplierReturn->status) {
                                    'pending' => 'bg-warning text-dark',
                                    'completed' => 'bg-success',
                                    'cancelled' => 'bg-secondary',
                                } }}">
                                    {{ ucfirst($supplierReturn->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.supplier-returns.show', $supplierReturn) }}" class="btn btn-outline-secondary btn-sm">{{ __('View') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-0">
                                <x-empty-state icon="bi-box-arrow-left" :title="__('No supplier returns yet')" :text="__('Create one to send dead or damaged stock back to a supplier.')">
                                    <x-slot name="action">
                                        <a href="{{ route('admin.supplier-returns.create') }}" class="btn btn-primary btn-sm">{{ __('New Supplier Return') }}</a>
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
        {{ $supplierReturns->links() }}
    </div>
</x-admin-layout>
