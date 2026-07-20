{{--
    Shared Returns & Refunds screen for both admin and cashier (wrapped by
    admin/returns/index.blade.php and cashier/returns/index.blade.php with
    their own layout/sidebar). Look up a sale by invoice number, pick
    quantities to return, and submit — the server computes the exact
    proportional refund (discount/tax/points-redeemed all accounted for).
--}}
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body">
                <x-input-label for="invoice-lookup" :value="__('Find a sale by invoice number')" />
                <div class="input-group">
                    <input type="text" id="invoice-lookup" class="form-control" placeholder="{{ __('e.g. INV-20260719-0008') }}" autocomplete="off">
                    <button type="button" class="btn btn-primary" id="invoice-lookup-btn">{{ __('Find Sale') }}</button>
                </div>
                <div class="small text-danger mt-2 d-none" id="lookup-error"></div>
            </div>
        </div>

        <div class="card d-none" id="return-form-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="fw-semibold" id="sale-invoice-no"></div>
                        <div class="small text-muted" id="sale-meta"></div>
                    </div>
                </div>

                <form method="POST" action="{{ route('returns.store') }}" id="return-form">
                    @csrf
                    <input type="hidden" name="sale_id" id="sale_id_input">
                    <div id="items-inputs"></div>

                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Product') }}</th>
                                    <th class="text-end">{{ __('Sold') }}</th>
                                    <th class="text-end">{{ __('Already Returned') }}</th>
                                    <th style="width: 110px;">{{ __('Qty to Return') }}</th>
                                </tr>
                            </thead>
                            <tbody id="return-items-body"></tbody>
                        </table>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-input-label for="reason" :value="__('Reason (optional)')" />
                            <textarea name="reason" id="reason" class="form-control" rows="2" maxlength="1000"></textarea>
                        </div>
                        <div class="col-md-6">
                            <x-input-label for="refund_method" :value="__('Refund Method')" />
                            <select name="refund_method" id="refund_method" class="form-select">
                                <option value="cash">{{ __('Cash') }}</option>
                                <option value="card">{{ __('Card') }}</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                            <div class="form-text">{{ __('Estimated refund (before discount/tax adjustment): ') }}<strong id="estimated-refund">0.00</strong></div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-danger">{{ __('Process Return') }}</button>
                        <button type="button" class="btn btn-outline-secondary" id="cancel-return-btn">{{ __('Cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-1"></i> {{ __('Recent Returns') }}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Return #') }}</th>
                            <th>{{ __('Invoice') }}</th>
                            <th class="text-end">{{ __('Refunded') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($returns as $return)
                            <tr>
                                <td>{{ $return->return_no }}</td>
                                <td>{{ $return->sale->invoice_no }}</td>
                                <td class="text-end">{{ number_format($return->total_refunded, 2) }}</td>
                                <td><a href="{{ route('returns.show', $return) }}" class="btn btn-outline-secondary btn-sm">{{ __('View') }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-0"><x-empty-state icon="bi-arrow-return-left" :title="__('No returns yet')" :text="__('Processed returns will show up here.')" /></td></tr>
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
                    formCard.classList.add('d-none');
                    lookupError.textContent = data.message || 'Sale not found.';
                    lookupError.classList.remove('d-none');
                    return;
                }
                renderSale(data);
            });
    }

    function renderSale(sale) {
        currentSale = sale;
        saleIdInput.value = sale.sale_id;
        saleInvoiceNo.textContent = `${sale.invoice_no}`;
        saleMeta.textContent = `${sale.date} · ${sale.customer_name} · ${sale.payment_method} · Total: ${sale.total.toFixed(2)}`;
        refundMethodSelect.value = sale.payment_method;

        itemsBody.innerHTML = '';
        itemsInputs.innerHTML = '';
        sale.items.forEach((item, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.product_name}</td>
                <td class="text-end">${item.quantity}</td>
                <td class="text-end">${item.already_returned}</td>
                <td><input type="number" class="form-control form-control-sm qty-return-input" data-price="${item.unit_price}" min="0" max="${item.max_returnable}" value="0" ${item.max_returnable === 0 ? 'disabled' : ''}></td>
            `;
            itemsBody.appendChild(row);

            const hiddenSaleItemId = document.createElement('input');
            hiddenSaleItemId.type = 'hidden';
            hiddenSaleItemId.name = `items[${index}][sale_item_id]`;
            hiddenSaleItemId.value = item.sale_item_id;
            itemsInputs.appendChild(hiddenSaleItemId);

            const qtyInput = row.querySelector('.qty-return-input');
            const hiddenQty = document.createElement('input');
            hiddenQty.type = 'hidden';
            hiddenQty.name = `items[${index}][quantity]`;
            hiddenQty.value = 0;
            itemsInputs.appendChild(hiddenQty);

            qtyInput.addEventListener('input', () => {
                let qty = parseInt(qtyInput.value) || 0;
                qty = Math.max(0, Math.min(qty, item.max_returnable));
                qtyInput.value = qty;
                hiddenQty.value = qty;
                recalcEstimate();
            });
        });

        formCard.classList.remove('d-none');
        recalcEstimate();
    }

    function recalcEstimate() {
        let total = 0;
        itemsBody.querySelectorAll('.qty-return-input').forEach(input => {
            total += (parseInt(input.value) || 0) * parseFloat(input.dataset.price);
        });
        estimatedRefund.textContent = total.toFixed(2);
    }

    lookupBtn.addEventListener('click', performLookup);
    lookupInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            performLookup();
        }
    });

    document.getElementById('cancel-return-btn').addEventListener('click', () => {
        formCard.classList.add('d-none');
        lookupInput.value = '';
        currentSale = null;
    });
</script>
