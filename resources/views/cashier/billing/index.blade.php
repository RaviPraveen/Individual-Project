<x-cashier-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h2 class="h3 mb-0 fw-extrabold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-cart-check-fill text-primary"></i> {{ __('POS Register Terminal') }}
                </h2>
                <div class="text-muted small">{{ __('Scan items, process payments, and serve customers with maximum speed.') }}</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-2" id="open-display-btn" onclick="window.open('{{ route('cashier.display.show') }}', 'customer_display', 'width=1200,height=800,resizable=yes,scrollbars=yes');">
                    <i class="bi bi-display me-1"></i> {{ __('Customer Display') }}
                </button>
            </div>
        </div>
    </x-slot>

    <x-barcode-scan-modal />

    <form method="POST" action="{{ route('cashier.billing.store') }}" id="billing-form">
        @csrf
        <div id="items-inputs"></div>
        <input type="hidden" name="customer_id" id="customer_id_input">
        <input type="hidden" name="points_to_redeem" id="points_to_redeem_input_hidden" value="0">

        <div class="row g-4">
            <!-- LEFT & CENTER: Barcode Search & Dominant Shopping Cart (col-lg-8) -->
            <div class="col-lg-8">
                @if ($geminiConfigured)
                    <!-- Compact AI Quick Order Drawer -->
                    <div class="card border-0 shadow-xs mb-3 bg-white" style="border: 1px solid #DBEAFE !important;">
                        <div class="card-body p-2.5">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="fw-bold small text-primary d-flex align-items-center gap-1.5">
                                    <i class="bi bi-stars text-warning fs-6"></i> {{ __('AI Quick Order') }}
                                </span>
                            </div>
                            <div class="input-group">
                                <input type="text" id="ai-order-text" class="form-control border-end-0 bg-light" placeholder="{{ __('Type order in plain words, e.g. 2kg rice, 1 milk powder, 3 sugar...') }}" autocomplete="off" style="font-size: 0.9rem; height: 38px;">
                                <button type="button" class="btn btn-primary px-3 fw-bold btn-sm" id="ai-order-parse-btn">
                                    <i class="bi bi-magic me-1"></i> {{ __('Parse') }}
                                </button>
                            </div>
                            <div class="small text-danger mt-1.5 d-none fw-semibold" id="ai-order-error"></div>

                            <div class="mt-2.5 d-none p-3 bg-white rounded-3 border" id="ai-order-preview">
                                <div class="small text-muted mb-2 fw-bold text-uppercase" style="letter-spacing:0.05em;">{{ __('Review Parsed Items:') }}</div>
                                <div id="ai-order-preview-list" class="list-group mb-3"></div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-primary btn-sm rounded-2 px-3" id="ai-order-add-btn"><i class="bi bi-cart-plus me-1"></i> {{ __('Add to Cart') }}</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-2 px-3" id="ai-order-cancel-btn">{{ __('Cancel') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Product Barcode Scan & Live Search Input -->
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-body p-3 position-relative">
                        <div class="d-flex justify-content-between align-items-center mb-1.5">
                            <label for="product-search" class="form-label fw-bold mb-0 text-dark d-flex align-items-center gap-1.5">
                                <i class="bi bi-upc-scan text-primary fs-5"></i> {{ __('Scan Barcode or Search Product') }}
                            </label>
                            <span class="badge bg-secondary-subtle text-muted font-monospace small"><i class="bi bi-keyboard me-1"></i> Press ENTER to add</span>
                        </div>
                        <div class="input-group pos-billing-search-container border rounded-3 overflow-hidden">
                            <span class="input-group-text bg-white border-0 text-muted px-3"><i class="bi bi-barcode fs-4 text-primary"></i></span>
                            <input type="text" id="product-search" class="form-control border-0 ps-0 text-dark fw-semibold pos-billing-search-input" placeholder="{{ __('Scan barcode sticker or type product name / SKU...') }}" autocomplete="off" autofocus>
                            <button type="button" class="btn btn-outline-primary border-0 border-start" id="scan-camera-btn" title="{{ __('Scan Barcode with Camera') }}" data-bs-toggle="tooltip">
                                <i class="bi bi-camera-fill"></i>
                            </button>
                        </div>
                        <div id="product-results" class="list-group position-absolute w-100 shadow-lg border-0 rounded-3 overflow-hidden mt-1" style="z-index: 1050; left:0; right:0;"></div>
                    </div>
                </div>

                <!-- Dominant Shopping Cart Table (Min-Height 500px) -->
                <div class="card shadow-sm border-0 mb-4" style="min-height: 480px;">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                        <div class="fw-bold h5 mb-0 text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-basket3-fill text-primary"></i> {{ __('Active Shopping Cart') }}
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-2 px-3" id="clear-cart-btn">
                            <i class="bi bi-trash me-1"></i> {{ __('Clear Cart') }}
                        </button>
                    </div>
                    <div class="table-responsive flex-grow-1">
                        <table class="table table-hover align-middle mb-0 pos-billing-cart-table" id="cart-table">
                            <thead class="bg-light sticky-top">
                                <tr>
                                    <th class="ps-4 py-3">{{ __('Product Description') }}</th>
                                    <th style="width: 150px;" class="text-center py-3">{{ __('Quantity') }}</th>
                                    <th class="text-end py-3">{{ __('Unit Price') }}</th>
                                    <th class="text-end py-3">{{ __('Line Total') }}</th>
                                    <th style="width: 50px;" class="pe-4 text-center py-3"></th>
                                </tr>
                            </thead>
                            <tbody id="cart-body">
                                <tr id="cart-empty-row">
                                    <td colspan="5" class="p-0">
                                        <div class="p-5 text-center my-4">
                                            <div class="display-4 text-muted mb-2"><i class="bi bi-box-seam text-primary-subtle"></i></div>
                                            <div class="h5 fw-bold text-dark mb-1">{{ __('Shopping Cart is Empty') }}</div>
                                            <div class="text-muted small mb-3">{{ __('Scan a barcode sticker or search for a product above to begin billing.') }}</div>
                                            <button type="button" onclick="document.getElementById('product-search').focus()" class="btn btn-outline-primary btn-sm rounded-2 fw-bold px-3">
                                                <i class="bi bi-search me-1"></i> {{ __('Browse Products') }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Sticky Payment Panel (col-lg-4) -->
            <div class="col-lg-4">
                <div class="card shadow-md border-0 sticky-top" style="top: 80px;">
                    <div class="card-body p-4">
                        <!-- Customer Loyalty Section -->
                        <div class="mb-3">
                            <label for="customer-search" class="form-label fw-bold text-dark d-flex align-items-center justify-content-between mb-1.5">
                                <span><i class="bi bi-person-badge me-1 text-primary"></i> {{ __('Customer Loyalty') }}</span>
                                <span class="badge badge-gold"><i class="bi bi-star-fill me-1"></i> Star Points</span>
                            </label>
                            <div class="position-relative">
                                <input type="text" id="customer-search" class="form-control" autocomplete="off" placeholder="{{ __('Enter phone number...') }}">
                                <div id="customer-results" class="list-group position-absolute w-100 shadow-lg rounded-3 overflow-hidden" style="z-index: 1050;"></div>
                            </div>

                            <!-- Selected Customer Panel -->
                            <div id="customer-selected-panel" class="d-none mt-3 p-3 pos-points-panel">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold text-dark" id="customer-selected-name"></div>
                                        <div class="small text-muted mt-0.5">
                                            <i class="bi bi-star-fill text-gold me-1"></i>
                                            <span id="customer-selected-points" class="fw-bold text-dark">0</span> {{ __('pts available') }}
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-2 px-2 py-0.5" id="customer-clear-btn">{{ __('Change') }}</button>
                                </div>

                                <div class="mt-3 pt-2 border-top border-warning-subtle">
                                    <label class="form-label small fw-bold mb-1 text-dark">{{ __('Redeem Points (1 pt = :val)', ['val' => number_format($pointsRedeemValue, 2)]) }}</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" id="points_to_redeem_input" class="form-control" min="0" value="0">
                                        <button type="button" class="btn btn-primary fw-bold" id="redeem-max-btn">{{ __('Max') }}</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Create Customer Panel -->
                            <div id="customer-quick-create" class="d-none mt-3 p-3 bg-light rounded-3 border">
                                <div class="small fw-bold text-dark mb-2"><i class="bi bi-person-plus text-primary me-1"></i> {{ __('Enroll New Member') }}</div>
                                <input type="text" id="quick-create-name" class="form-control form-control-sm mb-2" placeholder="{{ __('Full name') }}">
                                <input type="text" id="quick-create-phone" class="form-control form-control-sm mb-2" placeholder="{{ __('Phone number') }}">
                                <button type="button" class="btn btn-sm btn-primary w-100 fw-bold rounded-2" id="quick-create-btn">{{ __('Enroll & Select') }}</button>
                            </div>
                        </div>

                        <!-- Discount % Input -->
                        <div class="mb-3">
                            <label for="discount_percent" class="form-label fw-bold text-dark d-flex justify-content-between mb-1">
                                <span><i class="bi bi-percent me-1 text-primary"></i> {{ __('Discount %') }}</span>
                                <span class="small text-muted">Max: {{ $maxDiscountPercent }}%</span>
                            </label>
                            <input type="number" name="discount_percent" id="discount_percent" class="form-control fw-bold" min="0" max="{{ $maxDiscountPercent }}" step="0.01" value="0">
                        </div>

                        <!-- Payment Method Selector -->
                        <div class="mb-3">
                            <label for="payment_method" class="form-label fw-bold text-dark mb-1">
                                <i class="bi bi-credit-card me-1 text-primary"></i> {{ __('Payment Method') }}
                            </label>
                            <select name="payment_method" id="payment_method" class="form-select fw-bold" required style="font-size:0.95rem;">
                                <option value="cash">💵 {{ __('Cash') }}</option>
                                <option value="card">💳 {{ __('Card') }}</option>
                                <option value="other">📱 {{ __('Other / Digital') }}</option>
                            </select>
                        </div>

                        @if ($bagFee > 0)
                            <div class="form-check form-switch mb-3 p-2 bg-light rounded-2 border ps-5">
                                <input type="checkbox" name="wants_bag" value="1" class="form-check-input" id="wants_bag_input" style="margin-left:-2.5em;">
                                <label class="form-check-label fw-semibold text-dark small" for="wants_bag_input">
                                    {{ __('Add Carry Bag (+Rs :fee)', ['fee' => number_format($bagFee, 2)]) }}
                                </label>
                            </div>
                        @endif

                        <hr class="my-3">

                        <!-- Breakdown Summary Lines -->
                        <div class="d-flex justify-content-between text-muted mb-1 small">
                            <span>{{ __('Subtotal') }}</span>
                            <span id="summary-subtotal" class="fw-semibold text-dark">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted mb-1 small">
                            <span>{{ __('Discount') }}</span>
                            <span id="summary-discount" class="fw-semibold text-danger">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted mb-1 small d-none" id="summary-points-row">
                            <span><i class="bi bi-star-fill text-gold me-1"></i> {{ __('Points Redeemed') }}</span>
                            <span id="summary-points-redeemed" class="fw-semibold text-success">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted mb-1 small">
                            <span>{{ __('Tax (').$taxPercent.'%)' }}</span>
                            <span id="summary-tax" class="fw-semibold text-dark">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted mb-2 small d-none" id="summary-bag-fee-row">
                            <span><i class="bi bi-bag me-1"></i> {{ __('Bag Fee') }}</span>
                            <span id="summary-bag-fee" class="fw-semibold text-dark">0.00</span>
                        </div>

                        <!-- Dominant Total Amount Card -->
                        <div class="pos-total-card mb-3">
                            <div class="d-flex justify-content-between align-items-baseline">
                                <span class="fw-bold text-uppercase small" style="letter-spacing:0.08em; opacity: 0.9;">{{ __('TOTAL AMOUNT') }}</span>
                                <span class="pos-total-amount" id="summary-total">0.00</span>
                            </div>
                        </div>

                        <!-- 60px Height Primary Checkout Action Button -->
                        <button type="submit" class="btn pos-checkout-btn w-100" id="complete-sale-btn">
                            <i class="bi bi-check-circle-fill me-2"></i> {{ __('Complete Sale') }}
                        </button>
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
            if (!products || products.length === 0) {
                productResults.innerHTML = '<div class="list-group-item text-muted small py-3 text-center">{{ __('No products found') }}</div>';
                return;
            }

            products.forEach(p => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 px-3 border-bottom';
                item.innerHTML = `
                    <div>
                        <div class="fw-bold text-dark mb-0.5">${p.name}</div>
                        <div class="small text-muted">SKU: ${p.sku || '-'} | Barcode: ${p.barcode || '-'}</div>
                    </div>
                    <div class="text-end">
                        <div class="fw-extrabold text-primary">Rs ${parseFloat(p.selling_price).toFixed(2)}</div>
                        <span class="badge ${p.stock_qty > 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'} rounded-pill" style="font-size:0.72rem;">
                            Stock: ${p.stock_qty}
                        </span>
                    </div>
                `;
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
                } else {
                    window.posToast ? window.posToast('Maximum available stock reached.', 'warning') : alert('Maximum stock reached.');
                }
            } else {
                if (product.stock_qty < 1) {
                    window.posToast ? window.posToast('This product is out of stock.', 'danger') : alert('Out of stock.');
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
                    '<div class="pos-empty"><div class="pos-empty-icon"><i class="bi bi-cart3"></i></div>' +
                    '<div class="pos-empty-title">{{ __('Cart is empty') }}</div>' +
                    '<div class="pos-empty-text">{{ __('Scan a barcode or search a product to add it to cart.') }}</div></div></td></tr>';
            }

            cart.forEach((item, index) => {
                const row = document.createElement('tr');
                row.className = 'align-middle';
                row.innerHTML = `
                    <td class="ps-4">
                        <div class="fw-bold text-dark">${item.name}</div>
                        <div class="small text-muted">SKU: ${item.sku || '-'}</div>
                    </td>
                    <td class="text-center">
                        <div class="qty-stepper">
                            <button type="button" class="btn-qty-minus">-</button>
                            <input type="number" class="qty-input" min="1" max="${item.stock_qty}" value="${item.quantity}">
                            <button type="button" class="btn-qty-plus">+</button>
                        </div>
                    </td>
                    <td class="text-end fw-semibold text-dark">${item.unit_price.toFixed(2)}</td>
                    <td class="text-end fw-bold text-primary line-total">${(item.unit_price * item.quantity).toFixed(2)}</td>
                    <td class="pe-4 text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-circle remove-item" style="width:32px;height:32px;padding:0;">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </td>
                `;

                const qtyInput = row.querySelector('.qty-input');
                const minusBtn = row.querySelector('.btn-qty-minus');
                const plusBtn = row.querySelector('.btn-qty-plus');

                function updateQty(newQty) {
                    let qty = parseInt(newQty) || 1;
                    if (qty > item.stock_qty) qty = item.stock_qty;
                    if (qty < 1) qty = 1;
                    qtyInput.value = qty;
                    item.quantity = qty;
                    row.querySelector('.line-total').textContent = (item.unit_price * item.quantity).toFixed(2);
                    rebuildItemsInputs();
                    recalculateSummary();
                }

                qtyInput.addEventListener('input', (e) => updateQty(e.target.value));
                minusBtn.addEventListener('click', () => updateQty(item.quantity - 1));
                plusBtn.addEventListener('click', () => updateQty(item.quantity + 1));

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

            syncCustomerDisplay({
                subtotal, discount: discountAmount, tax: taxAmount, total: finalTotal, bagFee: bagFeeAmount,
                points_preview: selectedCustomer ? pointsEarnPreview : null,
            });
        }

        /* ---------- Customer Display Sync ---------- */
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
                        bag_fee: summary.bagFee,
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
        if (wantsBagInput) {
            wantsBagInput.addEventListener('change', recalculateSummary);
        }

        document.getElementById('redeem-max-btn').addEventListener('click', () => {
            pointsToRedeemInput.value = pointsToRedeemInput.max || 0;
            recalculateSummary();
        });

        document.getElementById('clear-cart-btn').addEventListener('click', () => {
            if (cart.length > 0) {
                cart = [];
                renderCart();
            }
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
        }

        document.getElementById('customer-clear-btn').addEventListener('click', () => {
            selectedCustomer = null;
            document.getElementById('customer_id_input').value = '';
            customerSelectedPanel.classList.add('d-none');
            recalculateSummary();
        });

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
                    item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3';
                    item.innerHTML = `<span>${c.name} <small class="text-muted">(${c.phone})</small></span> <span class="badge bg-gold-subtle"><i class="bi bi-star-fill text-gold me-1"></i> ${c.points_balance} pts</span>`;
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
                window.posToast ? window.posToast('Add at least one product to the cart before completing the sale.', 'warning') : alert('Add at least one product.');
            }
        });

        /* ---------- AI Quick Order ---------- */
        const aiOrderTextInput = document.getElementById('ai-order-text');
        const aiOrderParseBtn = document.getElementById('ai-order-parse-btn');

        if (aiOrderParseBtn) {
            const parseOrderUrl = '{{ route('cashier.billing.parse-order') }}';
            const aiOrderError = document.getElementById('ai-order-error');
            const aiOrderPreview = document.getElementById('ai-order-preview');
            const aiOrderPreviewList = document.getElementById('ai-order-preview-list');
            const aiOrderAddBtn = document.getElementById('ai-order-add-btn');
            const aiOrderCancelBtn = document.getElementById('ai-order-cancel-btn');

            let aiParsedItems = [];

            function renderAiPreview() {
                aiOrderPreviewList.innerHTML = '';
                aiParsedItems.forEach((item, index) => {
                    const row = document.createElement('div');
                    row.className = 'list-group-item d-flex justify-content-between align-items-center py-2 px-3';
                    row.innerHTML = `
                        <span class="fw-bold text-dark">${item.name}</span>
                        <div class="d-flex align-items-center gap-2">
                            <input type="number" class="form-control form-control-sm ai-qty-input fw-bold" style="width:70px;" min="1" max="${item.stock_qty}" value="${item.quantity}">
                            <button type="button" class="btn btn-outline-danger btn-sm rounded-circle ai-remove-btn" style="width:28px;height:28px;padding:0;"><i class="bi bi-x"></i></button>
                        </div>
                    `;
                    row.querySelector('.ai-qty-input').addEventListener('input', (e) => {
                        let qty = parseInt(e.target.value) || 1;
                        qty = Math.max(1, Math.min(qty, item.stock_qty));
                        e.target.value = qty;
                        item.quantity = qty;
                    });
                    row.querySelector('.ai-remove-btn').addEventListener('click', () => {
                        aiParsedItems.splice(index, 1);
                        renderAiPreview();
                    });
                    aiOrderPreviewList.appendChild(row);
                });

                if (aiParsedItems.length === 0) {
                    aiOrderPreview.classList.add('d-none');
                }
            }

            function resetAiOrderBox() {
                aiParsedItems = [];
                aiOrderTextInput.value = '';
                aiOrderPreview.classList.add('d-none');
                aiOrderError.classList.add('d-none');
            }

            aiOrderParseBtn.addEventListener('click', () => {
                const text = aiOrderTextInput.value.trim();
                aiOrderError.classList.add('d-none');
                if (!text) return;

                aiOrderParseBtn.disabled = true;
                aiOrderParseBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Parsing...';
                const csrf = document.querySelector('meta[name="csrf-token"]').content;

                fetch(parseOrderUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ text }),
                }).then(r => r.json()).then(data => {
                    aiOrderParseBtn.disabled = false;
                    aiOrderParseBtn.innerHTML = '<i class="bi bi-magic"></i> {{ __('Parse') }}';

                    if (data.error) {
                        aiOrderError.textContent = data.error;
                        aiOrderError.classList.remove('d-none');
                        return;
                    }

                    if (!data.items || data.items.length === 0) {
                        aiOrderError.textContent = '{{ __('No matching products found — try rephrasing or add items manually.') }}';
                        aiOrderError.classList.remove('d-none');
                        return;
                    }

                    aiParsedItems = data.items.map(item => ({ ...item, selling_price: parseFloat(item.selling_price) }));
                    aiOrderPreview.classList.remove('d-none');
                    renderAiPreview();
                }).catch(() => {
                    aiOrderParseBtn.disabled = false;
                    aiOrderParseBtn.innerHTML = '<i class="bi bi-magic"></i> {{ __('Parse') }}';
                    aiOrderError.textContent = '{{ __('Something went wrong. Please try again.') }}';
                    aiOrderError.classList.remove('d-none');
                });
            });

            aiOrderAddBtn.addEventListener('click', () => {
                aiParsedItems.forEach(item => {
                    addToCart({
                        id: item.product_id,
                        name: item.name,
                        sku: item.sku,
                        selling_price: item.selling_price,
                        stock_qty: item.stock_qty,
                    });

                    const cartItem = cart.find(c => c.product_id === item.product_id);
                    if (cartItem) {
                        cartItem.quantity = Math.max(1, Math.min(item.quantity, item.stock_qty));
                    }
                });
                renderCart();
                window.posToast ? window.posToast('Added items to cart from AI Quick Order.', 'success') : null;
                resetAiOrderBox();
            });

            aiOrderCancelBtn.addEventListener('click', resetAiOrderBox);

            aiOrderTextInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    aiOrderParseBtn.click();
                }
            });
        }

        renderCart();
    </script>

    <!-- Camera Barcode Scanner (additive — existing USB/Bluetooth/keyboard-wedge scanning above is untouched) -->
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="{{ asset('js/barcode-scanner.js') }}"></script>
    <script>
        document.getElementById('scan-camera-btn').addEventListener('click', () => {
            window.openBarcodeScanner((code) => {
                fetch(`${productSearchUrl}?q=${encodeURIComponent(code)}`)
                    .then(r => r.json())
                    .then(products => {
                        const exact = products.find(p => p.barcode === code || p.sku === code);
                        if (exact) {
                            addToCart(exact);
                            window.posToast ? window.posToast(`${exact.name} added to cart.`, 'success') : null;
                        } else {
                            window.posToast ? window.posToast('No product found for this barcode.', 'danger') : alert('No product found for this barcode.');
                        }
                    })
                    .catch(() => {
                        window.posToast ? window.posToast('Something went wrong looking up that barcode.', 'danger') : null;
                    });
            });
        });
    </script>
</x-cashier-layout>
