<x-admin-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('New Purchase Order') }}</h2>
    </x-slot>

    @if (! empty($prefill['items']))
        <div class="alert alert-info d-flex align-items-center gap-2" style="max-width: 60rem;">
            <i class="bi bi-robot"></i> {{ __('Line items pre-filled from the Smart Reorder Assistant — review and adjust before submitting.') }}
        </div>
    @endif

    <div class="card" style="max-width: 60rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.purchase-orders.store') }}" id="po-form">
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
                        <x-input-label for="order_date" :value="__('Order Date')" />
                        <x-text-input id="order_date" name="order_date" type="date" :value="old('order_date', now()->format('Y-m-d'))" required />
                        <x-input-error :messages="$errors->get('order_date')" />
                    </div>
                </div>

                <h3 class="h6 mt-4">{{ __('Line Items') }}</h3>
                <table class="table table-bordered" id="items-table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">{{ __('Product') }}</th>
                            <th>{{ __('Quantity') }}</th>
                            <th>{{ __('Unit Cost') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="items-body"></tbody>
                </table>
                <x-input-error :messages="$errors->get('items')" />

                <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="add-item">{{ __('Add Line') }}</button>

                <div class="mb-3">
                    {{ __('Total: ') }}<strong id="po-total">0.00</strong>
                </div>

                <div class="d-flex gap-2">
                    <x-primary-button>{{ __('Create Purchase Order') }}</x-primary-button>
                    <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const products = @json($products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'cost_price' => $p->cost_price]));
        const itemsBody = document.getElementById('items-body');
        const totalEl = document.getElementById('po-total');
        let rowIndex = 0;

        function productOptions(selected) {
            return products.map(p => `<option value="${p.id}" data-cost="${p.cost_price}" ${p.id == selected ? 'selected' : ''}>${p.name}</option>`).join('');
        }

        function addRow(productId = null, quantity = 1) {
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
                <td><input type="number" name="items[${index}][unit_cost]" class="form-control unit-cost-input" min="0" step="0.01" value="0" required></td>
                <td><button type="button" class="btn btn-outline-danger btn-sm remove-row">{{ __('Remove') }}</button></td>
            `;
            itemsBody.appendChild(row);

            if (productId) {
                const product = products.find(p => p.id === productId);
                row.querySelector('.unit-cost-input').value = product ? product.cost_price : 0;
            }
        }

        function recalculateTotal() {
            let total = 0;
            itemsBody.querySelectorAll('tr').forEach(row => {
                const qty = parseFloat(row.querySelector('.quantity-input')?.value) || 0;
                const cost = parseFloat(row.querySelector('.unit-cost-input')?.value) || 0;
                total += qty * cost;
            });
            totalEl.textContent = total.toFixed(2);
        }

        document.getElementById('add-item').addEventListener('click', () => {
            addRow();
            recalculateTotal();
        });

        itemsBody.addEventListener('click', (event) => {
            if (event.target.classList.contains('remove-row')) {
                event.target.closest('tr').remove();
                recalculateTotal();
            }
        });

        itemsBody.addEventListener('input', (event) => {
            if (event.target.classList.contains('quantity-input') || event.target.classList.contains('unit-cost-input')) {
                recalculateTotal();
            }
        });

        itemsBody.addEventListener('change', (event) => {
            if (event.target.classList.contains('product-select')) {
                const selectedOption = event.target.selectedOptions[0];
                const cost = selectedOption ? selectedOption.dataset.cost : 0;
                const row = event.target.closest('tr');
                row.querySelector('.unit-cost-input').value = cost || 0;
                recalculateTotal();
            }
        });

        const prefillItems = @json($prefill['items'] ?? []);
        if (prefillItems.length > 0) {
            prefillItems.forEach(item => addRow(item.product_id, item.quantity));
        } else {
            addRow();
        }
        recalculateTotal();
    </script>
</x-admin-layout>
