<x-admin-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Edit Product') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 45rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.products.update', $product) }}">
                @csrf
                @method('PUT')
                @include('admin.products._form')

                <div class="d-flex gap-2">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
