<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h2 class="h3 mb-0 fw-extrabold text-dark"><i class="bi bi-box-seam text-primary me-2"></i>{{ __('Products Catalog') }}</h2>
                <div class="text-muted small">{{ __('Manage your inventory, SKU pricing, barcodes, and stock levels.') }}</div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.products.import.form') }}" class="btn btn-outline-primary rounded-pill px-4">
                    <i class="bi bi-upload"></i> {{ __('Bulk Import') }}
                </a>
                <a href="{{ route('admin.products.create') }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
                    <i class="bi bi-plus-lg"></i> {{ __('Add New Product') }}
                </a>
            </div>
        </div>
    </x-slot>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3.5">
            <form method="GET" action="{{ route('admin.products.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <x-input-label for="filter_name" :value="__('Product Name')" />
                    <x-text-input id="filter_name" name="name" type="text" value="{{ request('name') }}" placeholder="{{ __('Search name...') }}" />
                </div>
                <div class="col-md-3">
                    <x-input-label for="filter_barcode" :value="__('Barcode / SKU')" />
                    <x-text-input id="filter_barcode" name="barcode" type="text" value="{{ request('barcode') }}" placeholder="{{ __('Scan or type barcode...') }}" />
                </div>
                <div class="col-md-3">
                    <x-input-label for="filter_category" :value="__('Category')" />
                    <select id="filter_category" name="category_id" class="form-select">
                        <option value="">{{ __('All Categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary fw-bold flex-grow-1"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button>
                    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Products Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">{{ __('Product Name') }}</th>
                        <th>{{ __('SKU') }}</th>
                        <th>{{ __('Barcode') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th class="text-end">{{ __('Cost') }}</th>
                        <th class="text-end">{{ __('Selling Price') }}</th>
                        <th class="text-center">{{ __('Stock Level') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="pe-4 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr class="{{ $product->isLowStock() ? 'table-warning-subtle' : '' }}">
                            <td class="ps-4 fw-bold text-dark">
                                {{ $product->name }}
                            </td>
                            <td class="font-monospace small text-muted">{{ $product->sku }}</td>
                            <td class="font-monospace small text-muted">{{ $product->barcode ?: '-' }}</td>
                            <td>
                                @if ($product->category)
                                    <span class="badge bg-light text-dark border">{{ $product->category->name }}</span>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="text-end font-monospace text-muted">Rs {{ number_format($product->cost_price, 2) }}</td>
                            <td class="text-end font-monospace fw-bold text-primary">Rs {{ number_format($product->selling_price, 2) }}</td>
                            <td class="text-center">
                                <span class="fw-bold">{{ $product->stock_qty }}</span> <small class="text-muted">{{ $product->unit }}</small>
                                @if ($product->isLowStock())
                                    <span class="badge bg-warning-subtle text-warning-emphasis ms-1">{{ __('Low Stock') }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $product->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $product->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-outline-secondary" title="{{ __('Edit Product') }}">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#adjustStock{{ $product->id }}" title="{{ __('Adjust Stock') }}">
                                        <i class="bi bi-sliders"></i>
                                    </button>
                                    <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Delete this product?') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="{{ __('Delete Product') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <!-- Adjust Stock Modal -->
                        <x-modal :name="'adjustStock'.$product->id">
                            <form method="POST" action="{{ route('admin.products.adjust-stock', $product) }}">
                                @csrf
                                <div class="modal-header border-bottom">
                                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-sliders text-primary me-2"></i>{{ __('Adjust Stock: ') }}{{ $product->name }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <div class="alert alert-light border small text-muted mb-3">
                                        <i class="bi bi-info-circle text-primary me-1"></i> Current stock: <strong>{{ $product->stock_qty }} {{ $product->unit }}</strong>
                                    </div>

                                    <div class="mb-3">
                                        <x-input-label :for="'quantity'.$product->id" :value="__('Quantity Adjustment (use negative to decrease)')" />
                                        <x-text-input :id="'quantity'.$product->id" name="quantity" type="number" required placeholder="e.g. 10 or -5" />
                                    </div>

                                    <div class="mb-3">
                                        <x-input-label :for="'reason'.$product->id" :value="__('Adjustment Reason')" />
                                        <x-text-input :id="'reason'.$product->id" name="reason" type="text" placeholder="e.g. damaged stock, shipment correction..." required />
                                    </div>
                                </div>
                                <div class="modal-footer border-top bg-light">
                                    <x-secondary-button data-bs-dismiss="modal">{{ __('Cancel') }}</x-secondary-button>
                                    <x-primary-button>{{ __('Apply Adjustment') }}</x-primary-button>
                                </div>
                            </form>
                        </x-modal>
                    @empty
                        <tr>
                            <td colspan="9" class="p-0">
                                <x-empty-state icon="bi-box-seam" :title="__('No products found')" :text="__('Try adjusting your search criteria, or add your first product.')">
                                    <x-slot name="action">
                                        <a href="{{ route('admin.products.create') }}" class="btn btn-primary rounded-pill px-4">{{ __('Add New Product') }}</a>
                                    </x-slot>
                                </x-empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $products->links() }}
    </div>
</x-admin-layout>
