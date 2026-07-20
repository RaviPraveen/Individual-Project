<x-admin-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Add Supplier') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 40rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.suppliers.store') }}">
                @csrf
                @include('admin.suppliers._form')

                <div class="d-flex gap-2">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
