<x-admin-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Add Customer') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 40rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.customers.store') }}">
                @csrf
                @include('admin.customers._form')

                <div class="d-flex gap-2">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
