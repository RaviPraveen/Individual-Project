<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ __('Customers') }}</h2>
            <a href="{{ route('admin.customers.create') }}" class="btn btn-primary btn-sm">{{ __('Add Customer') }}</a>
        </div>
    </x-slot>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Phone') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Star Points') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->phone }}</td>
                            <td>{{ $customer->email }}</td>
                            <td><span class="badge badge-gold"><i class="bi bi-star-fill"></i> {{ $customer->points_balance }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('admin.customers.behavior', $customer) }}" class="btn btn-outline-info btn-sm">{{ __('Behavior') }}</a>
                                <a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-outline-secondary btn-sm">{{ __('Edit') }}</a>
                                <form action="{{ route('admin.customers.destroy', $customer) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Delete this customer?') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-0">
                                <x-empty-state icon="bi-people" :title="__('No customers yet')">
                                    <x-slot name="action">
                                        <a href="{{ route('admin.customers.create') }}" class="btn btn-primary btn-sm">{{ __('Add Customer') }}</a>
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
        {{ $customers->links() }}
    </div>
</x-admin-layout>
