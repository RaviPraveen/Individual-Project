<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ __('Products') }}</h2>
            <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm">{{ __('Add Product') }}</a>
        </div>
    </x-slot>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.products.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <x-input-label for="filter_name" :value="__('Name')" />
                    <x-text-input id="filter_name" name="name" type="text" value="{{ request('name') }}" />
                </div>
                <div class="col-md-3">
                    <x-input-label for="filter_barcode" :value="__('Barcode')" />
                    <x-text-input id="filter_barcode" name="barcode" type="text" value="{{ request('barcode') }}" />
                </div>
                <div class="col-md-3">
                    <x-input-label for="filter_category" :value="__('Category')" />
                    <select id="filter_category" name="category_id" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary">{{ __('Filter') }}</button>
                    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('SKU') }}</th>
                        <th>{{ __('Barcode') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Cost') }}</th>
                        <th>{{ __('Price') }}</th>
                        <th>{{ __('Stock') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr class="{{ $product->isLowStock() ? 'table-warning' : '' }}">
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->sku }}</td>
                            <td>{{ $product->barcode }}</td>
                            <td>{{ $product->category?->name }}</td>
                            <td>{{ number_format($product->cost_price, 2) }}</td>
                            <td>{{ number_format($product->selling_price, 2) }}</td>
                            <td>
                                {{ $product->stock_qty }} {{ $product->unit }}
                                @if ($product->isLowStock())
                                    <span class="badge bg-warning text-dark">{{ __('Low') }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $product->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $product->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-outline-secondary btn-sm">{{ __('Edit') }}</a>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#adjustStock{{ $product->id }}">
                                    {{ __('Adjust Stock') }}
                                </button>
                                <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Delete this product?') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>

                        <x-modal :name="'adjustStock'.$product->id">
                            <form method="POST" action="{{ route('admin.products.adjust-stock', $product) }}">
                                @csrf
                                <div class="modal-header">
                                    <h2 class="h5 mb-0">{{ __('Adjust Stock: ') }}{{ $product->name }}</h2>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted small">{{ __('Current stock: ') }}{{ $product->stock_qty }} {{ $product->unit }}</p>

                                    <div class="mb-3">
                                        <x-input-label :for="'quantity'.$product->id" :value="__('Quantity change (use negative to reduce)')" />
                                        <x-text-input :id="'quantity'.$product->id" name="quantity" type="number" required />
                                    </div>

                                    <div class="mb-3">
                                        <x-input-label :for="'reason'.$product->id" :value="__('Reason')" />
                                        <x-text-input :id="'reason'.$product->id" name="reason" type="text" placeholder="e.g. damage, correction" required />
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <x-secondary-button data-bs-dismiss="modal">{{ __('Cancel') }}</x-secondary-button>
                                    <x-primary-button>{{ __('Apply') }}</x-primary-button>
                                </div>
                            </form>
                        </x-modal>
                    @empty
                        <tr>
                            <td colspan="9" class="p-0">
                                <x-empty-state icon="bi-box-seam" :title="__('No products found')" :text="__('Try a different search, or add your first product.')">
                                    <x-slot name="action">
                                        <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm">{{ __('Add Product') }}</a>
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
        {{ $products->links() }}
    </div>
</x-admin-layout>
