<x-admin-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Smart Reorder Assistant') }}</h2>
    </x-slot>

    <p class="text-muted small">{{ __('Products at or projected to fall below their reorder level in the next 30 days, sorted by urgency.') }}</p>

    @php
        $estimatedSpend = $suggestions->sum(fn ($f) => $f['recommended_reorder_qty'] * $f['product']->cost_price);
        $urgentCount = $suggestions->filter(fn ($f) => $f['projected_stock_30d'] < 0)->count();
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-4">
            <x-stat-card icon="bi-exclamation-triangle" tone="warning" :label="__('Need Reordering')" :value="$suggestions->count()" />
        </div>
        <div class="col-6 col-lg-4">
            <x-stat-card icon="bi-hourglass-split" tone="danger" :label="__('Urgent (Will Run Out)')" :value="$urgentCount" />
        </div>
        <div class="col-6 col-lg-4">
            <x-stat-card icon="bi-currency-exchange" tone="success" :label="__('Estimated Reorder Spend')" :value="number_format($estimatedSpend, 2)" />
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-robot me-1"></i> {{ __('AI Purchasing Recommendation') }}</div>
        <div class="card-body">
            @if ($narrative)
                <p class="mb-0">{{ $narrative }}</p>
            @elseif ($suggestions->isEmpty())
                <p class="mb-0 text-muted">{{ __('Nothing needs reordering right now.') }}</p>
            @else
                <p class="mb-0 text-muted">{{ __('AI narrative is currently unavailable (AI service not configured or unreachable). Review the figures below instead.') }}</p>
            @endif
        </div>
    </div>

    @if ($suggestions->isEmpty())
        <div class="card">
            <x-empty-state icon="bi-check2-circle" :title="__('Great — no products need reordering right now')" :text="__('Come back once stock levels change or new sales come in.')" />
        </div>
    @else
        <form id="reorder-form">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-truck me-1"></i> {{ __('Draft Purchase Order') }}</div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <x-input-label for="supplier_id" :value="__('Order from supplier')" />
                            <select id="supplier_id" class="form-select" required>
                                <option value="">{{ __('Select supplier') }}</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <button type="button" class="btn btn-primary" id="generate-po-btn">
                                <i class="bi bi-file-earmark-plus"></i> {{ __('Generate Draft Purchase Order') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-list-check me-1"></i> {{ __('Suggested Items') }}</div>
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" id="select-all" checked></th>
                                <th>{{ __('Product') }}</th>
                                <th>{{ __('Current Stock') }}</th>
                                <th>{{ __('Reorder Level') }}</th>
                                <th>{{ __('30-day Forecast') }}</th>
                                <th>{{ __('Usual Supplier') }}</th>
                                <th style="width: 110px;">{{ __('Order Qty') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($suggestions as $f)
                                <tr class="table-warning">
                                    <td><input type="checkbox" class="row-select" checked></td>
                                    <td>{{ $f['product']->name }}</td>
                                    <td>{{ $f['product']->stock_qty }}</td>
                                    <td>{{ $f['product']->reorder_level }}</td>
                                    <td>{{ $f['forecast_30d'] }}</td>
                                    <td>{{ $f['usual_supplier']['name'] ?? '—' }}</td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm qty-input" data-product-id="{{ $f['product']->id }}" min="1" value="{{ $f['recommended_reorder_qty'] }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    @endif

    <script>
        const createUrl = '{{ route('admin.purchase-orders.create') }}';

        const selectAll = document.getElementById('select-all');
        if (selectAll) {
            selectAll.addEventListener('change', () => {
                document.querySelectorAll('.row-select').forEach(cb => cb.checked = selectAll.checked);
            });
        }

        const generateBtn = document.getElementById('generate-po-btn');
        if (generateBtn) {
            generateBtn.addEventListener('click', () => {
                const supplierId = document.getElementById('supplier_id').value;
                if (!supplierId) {
                    window.posToast ? window.posToast('Select a supplier first.', 'warning') : alert('Select a supplier first.');
                    return;
                }

                const rows = document.querySelectorAll('#reorder-form tbody tr');
                const params = new URLSearchParams();
                params.set('supplier_id', supplierId);

                let index = 0;
                rows.forEach(row => {
                    const checked = row.querySelector('.row-select')?.checked;
                    const qtyInput = row.querySelector('.qty-input');
                    if (!checked || !qtyInput) return;

                    params.set(`items[${index}][product_id]`, qtyInput.dataset.productId);
                    params.set(`items[${index}][quantity]`, qtyInput.value);
                    index++;
                });

                if (index === 0) {
                    window.posToast ? window.posToast('Select at least one product.', 'warning') : alert('Select at least one product.');
                    return;
                }

                window.location.href = `${createUrl}?${params.toString()}`;
            });
        }
    </script>
</x-admin-layout>
