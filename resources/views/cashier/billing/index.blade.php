<x-cashier-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">{{ __('Billing') }}</h2>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="open-display-btn">
                <i class="bi bi-display"></i> {{ __('Open Customer Display') }}
            </button>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('cashier.billing.store') }}" id="billing-form">
        @csrf
        <div id="items-inputs"></div>
        <input type="hidden" name="customer_id" id="customer_id_input">
        <input type="hidden" name="points_to_redeem" id="points_to_redeem_input_hidden" value="0">

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-body position-relative">
                        <x-input-label for="product-search" :value="__('Scan barcode or search product (Enter to add)')" />
                        <input type="text" id="product-search" class="form-control" autocomplete="off" autofocus>
                        <div id="product-results" class="list-group position-absolute w-100" style="z-index: 1000; max-width: calc(100% - 2rem);"></div>
                    </div>
                </div>

                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" id="cart-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Product') }}</th>
                                    <th style="width: 120px;">{{ __('Qty') }}</th>
                                    <th>{{ __('Unit Price') }}</th>
                                    <th>{{ __('Line Total') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="cart-body">
                                <tr id="cart-empty-row">
                                    <td colspan="5" class="p-0"><x-empty-state icon="bi-cart" :title="__('Cart is empty')" :text="__('Scan or search a product to add it.')" /></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <div class="mb-2 position-relative">
                            <x-input-label for="customer-search" :value="__('Customer phone number (optional)')" />
                            <input type="text" id="customer-search" class="form-control" autocomplete="off" placeholder="{{ __('e.g. 0771234567') }}">
                            <div id="customer-results" class="list-group position-absolute w-100" style="z-index: 1000;"></div>
                        </div>

                        <div id="customer-selected-panel" class="d-none mb-3 p-2 pos-points-panel">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold" id="customer-selected-name"></div>
                                    <div class="small text-muted"><i class="bi bi-star-fill text-gold"></i> <span id="customer-selected-points">0</span> {{ __('points') }}</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="customer-clear-btn">{{ __('Change') }}</button>
                            </div>

                            <div class="mt-2">
                                <label class="form-label small mb-1">{{ __('Redeem points (1 pt = :value)', ['value' => number_format($pointsRedeemValue, 2)]) }}</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" id="points_to_redeem_input" class="form-control" min="0" value="0">
                                    <button type="button" class="btn btn-outline-primary" id="redeem-max-btn">{{ __('Max') }}</button>
                                </div>
                            </div>

                            <div class="mt-2 small d-none" id="upsell-suggestion-box">
                                <i class="bi bi-robot text-gold"></i>
                                <span id="upsell-suggestion-text" class="fst-italic"></span>
                            </div>
                        </div>

                        <div id="customer-quick-create" class="d-none mb-3 p-2 border rounded">
                            <div class="small text-muted mb-2">{{ __('No customer found with this number — enroll them in Star Points?') }}</div>
                            <input type="text" id="quick-create-name" class="form-control form-control-sm mb-2" placeholder="{{ __('Customer name') }}">
                            <input type="text" id="quick-create-phone" class="form-control form-control-sm mb-2" placeholder="{{ __('Phone number') }}">
                            <button type="button" class="btn btn-sm btn-primary w-100" id="quick-create-btn">{{ __('Enroll & Select') }}</button>
                        </div>

                        <div class="mb-3">
                            <x-input-label for="discount_percent" :value="__('Discount %')" />
                            <input type="number" name="discount_percent" id="discount_percent" class="form-control" min="0" max="{{ $maxDiscountPercent }}" step="0.01" value="0">
                            <div class="form-text">{{ __('Maximum allowed: ') }}{{ $maxDiscountPercent }}%</div>
                        </div>

                        <div class="mb-3">
                            <x-input-label for="payment_method" :value="__('Payment Method')" />
                            <select name="payment_method" id="payment_method" class="form-select" required>
                                <option value="cash">{{ __('Cash') }}</option>
                                <option value="card">{{ __('Card') }}</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                        </div>

                        @if ($bagFee > 0)
                            <div class="form-check mb-3">
                                <input type="checkbox" name="wants_bag" value="1" class="form-check-input" id="wants_bag_input">
                                <label class="form-check-label" for="wants_bag_input">{{ __('Customer wants a bag (+Rs :fee)', ['fee' => number_format($bagFee, 2)]) }}</label>
                            </div>
                        @endif

                        <hr>

                        <div class="d-flex justify-content-between">
                            <span>{{ __('Subtotal') }}</span>
                            <span id="summary-subtotal">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>{{ __('Discount') }}</span>
                            <span id="summary-discount">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between d-none" id="summary-points-row">
                            <span><i class="bi bi-star-fill text-gold"></i> {{ __('Points Redeemed') }}</span>
                            <span id="summary-points-redeemed">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>{{ __('Tax (').$taxPercent.'%)' }}</span>
                            <span id="summary-tax">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between d-none" id="summary-bag-fee-row">
                            <span><i class="bi bi-bag"></i> {{ __('Bag Fee') }}</span>
                            <span id="summary-bag-fee">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold fs-5">
                            <span>{{ __('Total') }}</span>
                            <span id="summary-total">0.00</span>
                        </div>
                        <div class="text-end small text-muted d-none" id="summary-points-earn-row">
                            {{ __("You'll earn ~") }}<span id="summary-points-earn">0</span> {{ __('points') }}
                        </div>

                        <div class="d-grid gap-2 mt-3">
                            <button type="submit" class="btn btn-primary" id="complete-sale-btn">{{ __('Complete Sale') }}</button>
                            <button type="button" class="btn btn-outline-danger" id="clear-cart-btn">{{ __('Clear Cart') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script>
        const maxDiscountPercent = {{ $maxDiscountPercent }};
        const taxPercent = {{ $taxPercent }};
        const pointsRedeemValue = {{ $pointsRedeemValue }};
        const pointsEarnPercent = {{ $pointsEarnPercent }};
        const bagFee = {{ $bagFee }};
        const productSearchUrl = '{{ route('products.search') }}';
        const customerSearchUrl = '{{ route('customers.search') }}';
        const quickCreateCustomerUrl = '{{ route('cashier.customers.quick-create') }}';

        let cart = [];
        let selectedCustomer = null;
        let searchDebounce = null;

        const productSearchInput = document.getElementById('product-search');
        const productResults = document.getElementById('product-results');
        const customerSearchInput = document.getElementById('customer-search');
        const customerResults = document.getElementById('customer-results');
        const customerSelectedPanel = document.getElementById('customer-selected-panel');
        const customerQuickCreate = document.getElementById('customer-quick-create');
        const pointsToRedeemInput = document.getElementById('points_to_redeem_input');
        const cartBody = document.getElementById('cart-body');
        const itemsInputs = document.getElementById('items-inputs');
        const discountInput = document.getElementById('discount_percent');
        const wantsBagInput = document.getElementById('wants_bag_input');

        function fetchJson(url, options) {
            return fetch(url, Object.assign({ headers: { 'Accept': 'application/json' } }, options)).then(r => r.json().then(data => ({ ok: r.ok, data })));
        }

        function debounce(fn, delay) {
            return (...args) => {
                clearTimeout(searchDebounce);
                searchDebounce = setTimeout(() => fn(...args), delay);
            };
        }

        function renderProductResults(products) {
            productResults.innerHTML = '';
            products.forEach(p => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.textContent = `${p.name} (${p.sku}) - stock: ${p.stock_qty}`;
                item.addEventListener('click', () => {
                    addToCart(p);
                    productSearchInput.value = '';
                    productResults.innerHTML = '';
                    productSearchInput.focus();
                });
                productResults.appendChild(item);
            });
        }

        function searchProducts(term) {
            if (!term) {
                productResults.innerHTML = '';
                return;
            }
            fetch(`${productSearchUrl}?q=${encodeURIComponent(term)}`).then(r => r.json()).then(renderProductResults);
        }

        productSearchInput.addEventListener('input', debounce((e) => searchProducts(e.target.value), 250));

        productSearchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const term = e.target.value.trim();
                if (!term) return;
                fetch(`${productSearchUrl}?q=${encodeURIComponent(term)}`).then(r => r.json()).then(products => {
                    const exact = products.find(p => p.barcode === term || p.sku === term);
                    if (exact) {
                        addToCart(exact);
                    } else if (products.length === 1) {
                        addToCart(products[0]);
                    } else {
                        renderProductResults(products);
                        return;
                    }
                    productSearchInput.value = '';
                    productResults.innerHTML = '';
                });
            }
        });

        function addToCart(product) {
            const existing = cart.find(c => c.product_id === product.id);
            if (existing) {
                if (existing.quantity < product.stock_qty) {
                    existing.quantity++;
                }
            } else {
                if (product.stock_qty < 1) {
                    alert('{{ __('This product is out of stock.') }}');
                    return;
                }
                cart.push({
                    product_id: product.id,
                    name: product.name,
                    sku: product.sku,
                    unit_price: parseFloat(product.selling_price),
                    stock_qty: product.stock_qty,
                    quantity: 1,
                });
            }
            renderCart();
        }

        function renderCart() {
            cartBody.innerHTML = '';

            if (cart.length === 0) {
                cartBody.innerHTML = '<tr id="cart-empty-row"><td colspan="5" class="p-0">' +
                    '<div class="pos-empty"><div class="pos-empty-icon"><i class="bi bi-cart"></i></div>' +
                    '<div class="pos-empty-title">{{ __('Cart is empty') }}</div>' +
                    '<div class="pos-empty-text">{{ __('Scan or search a product to add it.') }}</div></div></td></tr>';
            }

            cart.forEach((item, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${item.name} <span class="text-muted small">(${item.sku})</span></td>
                    <td><input type="number" class="form-control form-control-sm qty-input" min="1" max="${item.stock_qty}" value="${item.quantity}"></td>
                    <td>${item.unit_price.toFixed(2)}</td>
                    <td class="line-total">${(item.unit_price * item.quantity).toFixed(2)}</td>
                    <td><button type="button" class="btn btn-outline-danger btn-sm remove-item">{{ __('Remove') }}</button></td>
                `;

                row.querySelector('.qty-input').addEventListener('input', (e) => {
                    let qty = parseInt(e.target.value) || 1;
                    if (qty > item.stock_qty) {
                        qty = item.stock_qty;
                        e.target.value = qty;
                    }
                    if (qty < 1) {
                        qty = 1;
                        e.target.value = qty;
                    }
                    item.quantity = qty;
                    row.querySelector('.line-total').textContent = (item.unit_price * item.quantity).toFixed(2);
                    rebuildItemsInputs();
                    recalculateSummary();
                });

                row.querySelector('.remove-item').addEventListener('click', () => {
                    cart.splice(index, 1);
                    renderCart();
                });

                cartBody.appendChild(row);
            });

            rebuildItemsInputs();
            recalculateSummary();
        }

        function rebuildItemsInputs() {
            itemsInputs.innerHTML = '';
            cart.forEach((item, index) => {
                itemsInputs.innerHTML += `
                    <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">
                    <input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">
                `;
            });
        }

        function recalculateSummary() {
            const subtotal = cart.reduce((sum, item) => sum + item.unit_price * item.quantity, 0);
            let discountPercent = parseFloat(discountInput.value) || 0;
            if (discountPercent > maxDiscountPercent) {
                discountPercent = maxDiscountPercent;
                discountInput.value = maxDiscountPercent;
            }
            if (discountPercent < 0) {
                discountPercent = 0;
                discountInput.value = 0;
            }
            const discountAmount = subtotal * discountPercent / 100;
            const taxAmount = (subtotal - discountAmount) * taxPercent / 100;
            const totalBeforePoints = subtotal - discountAmount + taxAmount;

            let pointsToRedeem = 0;
            const pointsRow = document.getElementById('summary-points-row');
            const earnRow = document.getElementById('summary-points-earn-row');

            if (selectedCustomer) {
                const maxByBill = pointsRedeemValue > 0 ? Math.floor(totalBeforePoints / pointsRedeemValue) : 0;
                const maxRedeemable = Math.max(0, Math.min(selectedCustomer.points_balance, maxByBill));
                pointsToRedeemInput.max = maxRedeemable;

                pointsToRedeem = parseInt(pointsToRedeemInput.value) || 0;
                if (pointsToRedeem > maxRedeemable) {
                    pointsToRedeem = maxRedeemable;
                    pointsToRedeemInput.value = maxRedeemable;
                }
                if (pointsToRedeem < 0) {
                    pointsToRedeem = 0;
                    pointsToRedeemInput.value = 0;
                }
            } else {
                pointsToRedeemInput.value = 0;
            }

            document.getElementById('points_to_redeem_input_hidden').value = pointsToRedeem;
            const redemptionValue = pointsToRedeem * pointsRedeemValue;
            const total = totalBeforePoints - redemptionValue;

            // Bag fee is added after points are calculated — it's a flat
            // service fee, not part of the merchandise total points are
            // earned on. Mirrors BillingController::store()'s ordering.
            const wantsBag = wantsBagInput && wantsBagInput.checked;
            const bagFeeAmount = wantsBag ? bagFee : 0;
            const finalTotal = total + bagFeeAmount;

            pointsRow.classList.toggle('d-none', pointsToRedeem <= 0);
            document.getElementById('summary-points-redeemed').textContent = redemptionValue.toFixed(2);

            document.getElementById('summary-subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('summary-discount').textContent = discountAmount.toFixed(2);
            document.getElementById('summary-tax').textContent = taxAmount.toFixed(2);
            document.getElementById('summary-bag-fee-row').classList.toggle('d-none', !wantsBag);
            document.getElementById('summary-bag-fee').textContent = bagFeeAmount.toFixed(2);
            document.getElementById('summary-total').textContent = finalTotal.toFixed(2);

            const pointsEarnPreview = Math.floor(total * pointsEarnPercent / 100);
            if (selectedCustomer) {
                document.getElementById('summary-points-earn').textContent = pointsEarnPreview;
                earnRow.classList.remove('d-none');
            } else {
                earnRow.classList.add('d-none');
            }

            syncCustomerDisplay({
                subtotal, discount: discountAmount, tax: taxAmount, total: finalTotal, bagFee: bagFeeAmount,
                points_preview: selectedCustomer ? pointsEarnPreview : null,
            });
        }

        /* ---------- customer-facing display sync ---------- */
        const displaySyncUrl = '{{ route('cashier.display.sync') }}';
        const displayUrl = '{{ route('cashier.display.show') }}';
        let displaySyncTimer = null;

        document.getElementById('open-display-btn').addEventListener('click', () => {
            window.open(displayUrl, 'customer-display', 'popup');
        });

        function syncCustomerDisplay(summary) {
            clearTimeout(displaySyncTimer);
            displaySyncTimer = setTimeout(() => {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                fetch(displaySyncUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({
                        items: cart.map(item => ({
                            name: item.name,
                            quantity: item.quantity,
                            unit_price: item.unit_price,
                            line_total: item.unit_price * item.quantity,
                        })),
                        subtotal: summary.subtotal,
                        discount: summary.discount,
                        tax: summary.tax,
                        total: summary.total,
                        customer_name: selectedCustomer ? selectedCustomer.name : null,
                        points_balance: selectedCustomer ? selectedCustomer.points_balance : null,
                        points_preview: summary.points_preview,
                    }),
                }).catch(() => {});
            }, 400);
        }

        discountInput.addEventListener('input', recalculateSummary);
        pointsToRedeemInput.addEventListener('input', recalculateSummary);

        document.getElementById('redeem-max-btn').addEventListener('click', () => {
            pointsToRedeemInput.value = pointsToRedeemInput.max || 0;
            recalculateSummary();
        });

        document.getElementById('clear-cart-btn').addEventListener('click', () => {
            cart = [];
            renderCart();
        });

        function selectCustomer(customer) {
            selectedCustomer = customer;
            document.getElementById('customer_id_input').value = customer.id;
            document.getElementById('customer-selected-name').textContent = customer.name + (customer.phone ? ' (' + customer.phone + ')' : '');
            document.getElementById('customer-selected-points').textContent = customer.points_balance;
            customerSelectedPanel.classList.remove('d-none');
            customerQuickCreate.classList.add('d-none');
            customerSearchInput.value = '';
            customerResults.innerHTML = '';
            pointsToRedeemInput.value = 0;
            recalculateSummary();
            fetchUpsellSuggestion();
        }

        document.getElementById('customer-clear-btn').addEventListener('click', () => {
            selectedCustomer = null;
            document.getElementById('customer_id_input').value = '';
            customerSelectedPanel.classList.add('d-none');
            document.getElementById('upsell-suggestion-box').classList.add('d-none');
            recalculateSummary();
        });

        /* ---------- AI upsell suggestion ---------- */
        const upsellUrl = '{{ route('cashier.billing.upsell') }}';

        function fetchUpsellSuggestion() {
            const box = document.getElementById('upsell-suggestion-box');
            box.classList.add('d-none');
            if (!selectedCustomer) return;

            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            fetch(upsellUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({
                    customer_id: selectedCustomer.id,
                    cart_items: cart.map(item => item.name),
                }),
            }).then(r => r.json()).then(data => {
                if (data.suggestion) {
                    document.getElementById('upsell-suggestion-text').textContent = data.suggestion;
                    box.classList.remove('d-none');
                }
            }).catch(() => {});
        }

        customerSearchInput.addEventListener('input', debounce((e) => {
            const term = e.target.value.trim();
            customerResults.innerHTML = '';
            customerQuickCreate.classList.add('d-none');
            if (!term) return;

            fetch(`${customerSearchUrl}?q=${encodeURIComponent(term)}`).then(r => r.json()).then(customers => {
                if (customers.length === 0) {
                    document.getElementById('quick-create-name').value = '';
                    document.getElementById('quick-create-phone').value = term;
                    customerQuickCreate.classList.remove('d-none');
                    return;
                }

                customers.forEach(c => {
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'list-group-item list-group-item-action';
                    item.innerHTML = `${c.name}${c.phone ? ' (' + c.phone + ')' : ''} <span class="badge text-bg-light float-end"><i class="bi bi-star-fill text-gold"></i> ${c.points_balance}</span>`;
                    item.addEventListener('click', () => selectCustomer(c));
                    customerResults.appendChild(item);
                });
            });
        }, 300));

        document.getElementById('quick-create-btn').addEventListener('click', () => {
            const name = document.getElementById('quick-create-name').value.trim();
            const phone = document.getElementById('quick-create-phone').value.trim();

            if (!name || !phone) {
                alert('{{ __('Please enter both name and phone number.') }}');
                return;
            }

            const csrf = document.querySelector('meta[name="csrf-token"]').content;

            fetchJson(quickCreateCustomerUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ name, phone }),
            }).then(({ ok, data }) => {
                if (!ok) {
                    const message = data.errors ? Object.values(data.errors).flat().join(' ') : '{{ __('Could not create customer.') }}';
                    alert(message);
                    return;
                }
                selectCustomer(data);
            });
        });

        document.getElementById('billing-form').addEventListener('submit', (e) => {
            if (cart.length === 0) {
                e.preventDefault();
                alert('{{ __('Add at least one product to the cart before completing the sale.') }}');
            }
        });

        renderCart();
    </script>
</x-cashier-layout>
