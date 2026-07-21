{{--
    Shared Returns & Refunds screen for both admin and cashier
--}}
@if (isset($stats))
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-4">
            <x-stat-card icon="bi-arrow-return-left" tone="warning" :label="__('Total Returns Processed')" :value="$stats['total_count']" />
        </div>
        <div class="col-6 col-lg-4">
            <x-stat-card icon="bi-receipt-cutoff" tone="info" :label="__('Returns This Month')" :value="$stats['month_count']" />
        </div>
        <div class="col-6 col-lg-4">
            <x-stat-card icon="bi-cash-stack" tone="danger" :label="__('Total Refunded (Month)')" :value="'Rs '.number_format($stats['month_refunded'], 2)" />
        </div>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom fw-bold text-dark">
                <i class="bi bi-search text-primary me-1.5"></i> {{ __('Find Sale Invoice for Return') }}
            </div>
            <div class="card-body p-4">
                <x-input-label for="invoice-lookup" :value="__('Enter Invoice Number')" />
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-receipt"></i></span>
                    <input type="text" id="invoice-lookup" class="form-control border-start-0 ps-0 fw-semibold" placeholder="{{ __('e.g. INV-20260719-0008') }}" autocomplete="off">
                    <button type="button" class="btn btn-primary px-4 fw-bold" id="invoice-lookup-btn">
                        <i class="bi bi-search me-1"></i> {{ __('Find Sale') }}
                    </button>
                </div>
                <div class="small text-danger mt-2 d-none fw-semibold" id="lookup-error"></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm d-none" id="return-form-card">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-bold h5 mb-0 text-dark" id="sale-invoice-no"></div>
                    <div class="small text-muted" id="sale-meta"></div>
                </div>
                <span class="badge bg-primary-subtle text-primary">{{ __('Invoice Found') }}</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('returns.store') }}" id="return-form">
                    @csrf
                    <input type="hidden" name="sale_id" id="sale_id_input">
                    <div id="items-inputs"></div>

                    <div class="table-responsive mb-4">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>{{ __('Product Item') }}</th>
                                    <th class="text-end">{{ __('Sold Qty') }}</th>
                                    <th class="text-end">{{ __('Returned') }}</th>
                                    <th style="width: 120px;" class="text-center">{{ __('Qty to Return') }}</th>
                                </tr>
                            </thead>
                            <tbody id="return-items-body"></tbody>
                        </table>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <x-input-label for="reason" :value="__('Return Reason (Optional)')" />
                            <textarea name="reason" id="reason" class="form-control" rows="2" placeholder="{{ __('e.g. Defective item, wrong size...') }}" maxlength="1000"></textarea>
                        </div>
                        <div class="col-md-6">
                            <x-input-label for="refund_method" :value="__('Refund Method')" />
                            <select name="refund_method" id="refund_method" class="form-select fw-semibold">
                                <option value="cash">💵 {{ __('Cash') }}</option>
                                <option value="card">💳 {{ __('Card') }}</option>
                                <option value="other">📱 {{ __('Other / Digital') }}</option>
                            </select>
                            <div class="form-text mt-2">{{ __('Estimated refund: ') }}<strong id="estimated-refund" class="text-danger fs-6">0.00</strong></div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 pt-2 border-top">
                        <button type="submit" class="btn btn-danger fw-bold px-4 rounded-3"><i class="bi bi-check-circle me-1"></i> {{ __('Process Refund') }}</button>
                        <button type="button" class="btn btn-outline-secondary rounded-3" id="cancel-return-btn">{{ __('Cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom fw-bold text-dark">
                <i class="bi bi-clock-history me-1.5 text-primary"></i> {{ __('Recent Processed Returns') }}
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">{{ __('Return #') }}</th>
                            <th>{{ __('Invoice') }}</th>
                            <th class="text-end">{{ __('Refunded') }}</th>
                            <th class="pe-4 text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($returns as $return)
                            <tr>
                                <td class="ps-4 font-monospace small fw-bold text-dark">{{ $return->return_no }}</td>
                                <td class="font-monospace small text-muted">{{ $return->sale->invoice_no }}</td>
                                <td class="text-end fw-bold text-danger">Rs {{ number_format($return->total_refunded, 2) }}</td>
                                <td class="pe-4 text-end">
                                    <a href="{{ route('returns.show', $return) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">{{ __('View') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-0">
                                    <x-empty-state icon="bi-arrow-return-left" :title="__('No returns yet')" :text="__('Processed refunds will appear here.')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($returns->hasPages())
                <div class="card-body border-top">
                    {{ $returns->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    const lookupUrl = '{{ route('returns.lookup') }}';

    const lookupInput = document.getElementById('invoice-lookup');
    const lookupBtn = document.getElementById('invoice-lookup-btn');
    const lookupError = document.getElementById('lookup-error');
    const formCard = document.getElementById('return-form-card');
    const saleIdInput = document.getElementById('sale_id_input');
    const saleInvoiceNo = document.getElementById('sale-invoice-no');
    const saleMeta = document.getElementById('sale-meta');
    const itemsBody = document.getElementById('return-items-body');
    const itemsInputs = document.getElementById('items-inputs');
    const refundMethodSelect = document.getElementById('refund_method');
    const estimatedRefund = document.getElementById('estimated-refund');

    let currentSale = null;

    function performLookup() {
        const invoiceNo = lookupInput.value.trim();
        lookupError.classList.add('d-none');
        if (!invoiceNo) return;

        fetch(`${lookupUrl}?invoice_no=${encodeURIComponent(invoiceNo)}`, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json().then(data => ({ ok: r.ok, data })))
            .then(({ ok, data }) => {
                if (!ok) {
                    lookupError.textContent = data.message || '{{ __('Sale not found.') }}';
                    lookupError.classList.remove('d-none');
                    formCard.classList.add('d-none');
                    currentSale = null;
                    return;
                }
                currentSale = data;
                renderReturnForm();
            })
            .catch(() => {
                lookupError.textContent = '{{ __('Error looking up sale. Please try again.') }}';
                lookupError.classList.remove('d-none');
            });
    }

    lookupBtn.addEventListener('click', performLookup);
    lookupInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            performLookup();
        }
    });

    function renderReturnForm() {
        if (!currentSale) return;

        saleIdInput.value = currentSale.sale_id;
        saleInvoiceNo.textContent = currentSale.invoice_no;
        saleMeta.textContent = `Date: ${currentSale.date} | Customer: ${currentSale.customer_name || 'Walk-in'} | Total: Rs ${parseFloat(currentSale.total).toFixed(2)}`;

        itemsBody.innerHTML = '';
        currentSale.items.forEach(item => {
            const availableToReturn = item.max_returnable;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div class="fw-bold text-dark">${item.product_name}</div>
                    <div class="small text-muted">Price: Rs ${parseFloat(item.unit_price).toFixed(2)}</div>
                </td>
                <td class="text-end fw-semibold">${item.quantity}</td>
                <td class="text-end text-muted">${item.already_returned}</td>
                <td class="text-center">
                    <input type="number" class="form-control form-control-sm text-center fw-bold return-qty-input"
                           data-item-id="${item.sale_item_id}"
                           data-unit-price="${item.unit_price}"
                           min="0" max="${availableToReturn}" value="0"
                           ${availableToReturn <= 0 ? 'disabled' : ''}>
                </td>
            `;
            itemsBody.appendChild(row);
        });

        formCard.classList.remove('d-none');
        rebuildReturnInputs();
    }

    itemsBody.addEventListener('input', (e) => {
        if (e.target.classList.contains('return-qty-input')) {
            const max = parseInt(e.target.max) || 0;
            let val = parseInt(e.target.value) || 0;
            if (val > max) { val = max; e.target.value = val; }
            if (val < 0) { val = 0; e.target.value = val; }
            rebuildReturnInputs();
        }
    });

    function rebuildReturnInputs() {
        itemsInputs.innerHTML = '';
        let totalEstimated = 0;

        const inputs = itemsBody.querySelectorAll('.return-qty-input');
        inputs.forEach((input, index) => {
            const qty = parseInt(input.value) || 0;
            const itemId = input.dataset.itemId;
            const unitPrice = parseFloat(input.dataset.unitPrice) || 0;

            if (qty > 0) {
                itemsInputs.innerHTML += `
                    <input type="hidden" name="items[${index}][sale_item_id]" value="${itemId}">
                    <input type="hidden" name="items[${index}][quantity]" value="${qty}">
                `;
                totalEstimated += qty * unitPrice;
            }
        });

        estimatedRefund.textContent = totalEstimated.toFixed(2);
    }

    document.getElementById('cancel-return-btn').addEventListener('click', () => {
        formCard.classList.add('d-none');
        lookupInput.value = '';
        currentSale = null;
    });
</script>
