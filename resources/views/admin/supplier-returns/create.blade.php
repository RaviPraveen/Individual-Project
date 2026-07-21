<x-admin-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('New Supplier Return') }}</h2>
    </x-slot>

    @if (! empty($prefill['items']))
        <div class="alert alert-info d-flex align-items-center gap-2" style="max-width: 60rem;">
            <i class="bi bi-graph-up"></i> {{ __('Line item pre-filled from the Dead-Stock Report — review and adjust before submitting.') }}
        </div>
    @endif

    <div class="card" style="max-width: 60rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.supplier-returns.store') }}" id="sr-form">
                @csrf

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <x-input-label for="supplier_id" :value="__('Supplier')" />
                        <select id="supplier_id" name="supplier_id" class="form-select" required>
                            <option value="">{{ __('Select supplier') }}</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected(old('supplier_id', $prefill['supplier_id'] ?? null) == $supplier->id)>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('supplier_id')" />
                    </div>
                    <div class="col-md-6 mb-3">
                        <x-input-label for="return_date" :value="__('Return Date')" />
                        <x-text-input id="return_date" name="return_date" type="date" :value="old('return_date', now()->format('Y-m-d'))" required />
                        <x-input-error :messages="$errors->get('return_date')" />
                    </div>
                </div>

                <h3 class="h6 mt-4">{{ __('Returned Items') }}</h3>
                <table class="table table-bordered" id="items-table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">{{ __('Product') }}</th>
                            <th>{{ __('Quantity') }}</th>
                            <th>{{ __('Reason') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="items-body"></tbody>
                </table>
                <x-input-error :messages="$errors->get('items')" />

                <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="add-item">{{ __('Add Line') }}</button>

                <div class="d-flex gap-2">
                    <x-primary-button>{{ __('Create Supplier Return') }}</x-primary-button>
                    <a href="{{ route('admin.supplier-returns.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const products = @json($products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'stock_qty' => $p->stock_qty]));
        const reasons = [
            { value: 'expired', label: '{{ __('Expired') }}' },
            { value: 'damaged', label: '{{ __('Damaged') }}' },
            { value: 'near_expiry', label: '{{ __('Near Expiry') }}' },
            { value: 'not_selling', label: '{{ __('Not Selling') }}' },
            { value: 'wrong_item', label: '{{ __('Wrong Item') }}' },
        ];
        const itemsBody = document.getElementById('items-body');
        let rowIndex = 0;

        function productOptions(selected) {
            return products.map(p => `<option value="${p.id}" data-stock="${p.stock_qty}" ${p.id == selected ? 'selected' : ''}>${p.name} (stock: ${p.stock_qty})</option>`).join('');
        }

        function reasonOptions(selected) {
            return reasons.map(r => `<option value="${r.value}" ${r.value === selected ? 'selected' : ''}>${r.label}</option>`).join('');
        }

        function addRow(productId = null, quantity = 1, reason = 'not_selling') {
            const index = rowIndex++;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <select name="items[${index}][product_id]" class="form-select product-select" required>
                        <option value="">{{ __('Select product') }}</option>
                        ${productOptions(productId)}
                    </select>
                </td>
                <td><input type="number" name="items[${index}][quantity]" class="form-control quantity-input" min="1" value="${quantity}" required></td>
                <td>
                    <select name="items[${index}][reason]" class="form-select" required>
                        ${reasonOptions(reason)}
                    </select>
                </td>
                <td><button type="button" class="btn btn-outline-danger btn-sm remove-row">{{ __('Remove') }}</button></td>
            `;
            itemsBody.appendChild(row);
        }

        document.getElementById('add-item').addEventListener('click', () => addRow());

        itemsBody.addEventListener('click', (event) => {
            if (event.target.classList.contains('remove-row')) {
                event.target.closest('tr').remove();
            }
        });

        itemsBody.addEventListener('input', (event) => {
            if (event.target.classList.contains('quantity-input')) {
                const row = event.target.closest('tr');
                const select = row.querySelector('.product-select');
                const stock = parseInt(select.selectedOptions[0]?.dataset.stock) || 0;
                if (parseInt(event.target.value) > stock) {
                    event.target.setCustomValidity('{{ __('Quantity cannot exceed current stock.') }}');
                } else {
                    event.target.setCustomValidity('');
                }
            }
        });

        const prefillItems = @json($prefill['items'] ?? []);
        if (prefillItems.length > 0) {
            prefillItems.forEach(item => addRow(item.product_id, item.quantity, item.reason));
        } else {
            addRow();
        }
    </script>
</x-admin-layout>
