<x-cashier-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h2 class="h3 mb-0 fw-extrabold text-dark">{{ __('Shift Dashboard — Welcome, :name 👋', ['name' => explode(' ', $cashier->name)[0]]) }}</h2>
                <div class="text-muted small"><i class="bi bi-clock-history me-1 text-primary"></i> {{ $shift }} &middot; {{ now()->format('l, F j, Y') }}</div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-end border-end pe-3">
                    <div class="fs-4 fw-extrabold text-primary font-monospace" id="live-clock">{{ now()->format('h:i:s A') }}</div>
                    <div class="text-muted small fw-semibold">{{ __('Live Station Clock') }}</div>
                </div>
                <a href="{{ route('cashier.billing.index') }}" class="btn btn-primary btn-lg rounded-3 fw-bold px-4 shadow-sm">
                    <i class="bi bi-cart-check-fill me-1"></i> {{ __('Open Billing POS') }}
                </a>
            </div>
        </div>
    </x-slot>

    <!-- Shift Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-cash-stack" tone="success" :label="__('Today\'s Revenue')" :value="'Rs '.number_format($stats['sales_total'], 2)" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-receipt" tone="primary" :label="__('Bills Processed')" :value="$stats['bills_processed']" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-box-seam" tone="warning" :label="__('Items Sold')" :value="$stats['items_sold']" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-people" tone="info" :label="__('Customers Served')" :value="$stats['customers_served']" />
        </div>
    </div>

    <!-- Quick Action Cards Grid -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <a href="{{ route('cashier.billing.index') }}" class="card h-100 text-decoration-none border-0 shadow-sm quick-action">
                <div class="card-body text-center py-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-2" style="width:52px;height:52px;font-size:1.5rem;">
                        <i class="bi bi-cart-plus-fill"></i>
                    </div>
                    <div class="fw-bold text-dark mt-1">{{ __('New Billing POS') }}</div>
                    <div class="text-muted small">{{ __('Start customer transaction') }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="#" class="card h-100 text-decoration-none border-0 shadow-sm quick-action" data-bs-toggle="collapse" data-bs-target="#quickProductSearch" role="button">
                <div class="card-body text-center py-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-success-subtle text-success rounded-circle mb-2" style="width:52px;height:52px;font-size:1.5rem;">
                        <i class="bi bi-search"></i>
                    </div>
                    <div class="fw-bold text-dark mt-1">{{ __('Product Lookup') }}</div>
                    <div class="text-muted small">{{ __('Check stock & price') }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="#" class="card h-100 text-decoration-none border-0 shadow-sm quick-action" data-bs-toggle="collapse" data-bs-target="#quickCustomerSearch" role="button">
                <div class="card-body text-center py-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-info-subtle text-info rounded-circle mb-2" style="width:52px;height:52px;font-size:1.5rem;">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <div class="fw-bold text-dark mt-1">{{ __('Customer Search') }}</div>
                    <div class="text-muted small">{{ __('Check Star Points') }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="#recent-transactions" class="card h-100 text-decoration-none border-0 shadow-sm quick-action">
                <div class="card-body text-center py-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-warning-subtle text-warning-emphasis rounded-circle mb-2" style="width:52px;height:52px;font-size:1.5rem;">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="fw-bold text-dark mt-1">{{ __('Recent Bills') }}</div>
                    <div class="text-muted small">{{ __('Reprint receipt') }}</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Quick Lookup Collapsible Panels -->
    <div class="collapse mb-4" id="quickProductSearch">
        <div class="card border-0 shadow-md">
            <div class="card-body p-4">
                <label for="quick-product-input" class="form-label fw-bold text-dark mb-1"><i class="bi bi-search text-primary me-1"></i> {{ __('Quick Product Lookup (Name, SKU, or Barcode)') }}</label>
                <input type="text" id="quick-product-input" class="form-control form-control-lg" placeholder="{{ __('Type e.g. Rice 5kg or barcode...') }}">
                <div id="quick-product-results" class="list-group mt-2 shadow-xs rounded-3"></div>
            </div>
        </div>
    </div>

    <div class="collapse mb-4" id="quickCustomerSearch">
        <div class="card border-0 shadow-md">
            <div class="card-body p-4">
                <label for="quick-customer-input" class="form-label fw-bold text-dark mb-1"><i class="bi bi-person-badge text-primary me-1"></i> {{ __('Quick Customer Lookup (Name or Phone)') }}</label>
                <input type="text" id="quick-customer-input" class="form-control form-control-lg" placeholder="{{ __('Type e.g. 0771234567 or Nimal...') }}">
                <div id="quick-customer-results" class="list-group mt-2 shadow-xs rounded-3"></div>
            </div>
        </div>
    </div>

    <!-- Main Shift Lists & Side Panels -->
    <div class="row g-4">
        <!-- Left Column: Sales & History (col-lg-8) -->
        <div class="col-lg-8 d-flex flex-column gap-4">
            <!-- Today's Sales Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <span class="fw-bold text-dark h5 mb-0"><i class="bi bi-journal-text me-1.5 text-primary"></i> {{ __("My Today's Sales Log") }}</span>
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-3">{{ $todaysSales->count() }} {{ __('bills') }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">{{ __('Invoice') }}</th>
                                <th>{{ __('Time') }}</th>
                                <th>{{ __('Customer') }}</th>
                                <th>{{ __('Items') }}</th>
                                <th>{{ __('Payment') }}</th>
                                <th class="text-end">{{ __('Total') }}</th>
                                <th class="pe-4 text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($todaysSales as $sale)
                                <tr>
                                    <td class="ps-4 font-monospace small fw-bold text-dark">{{ $sale->invoice_no }}</td>
                                    <td>{{ $sale->created_at->format('h:i A') }}</td>
                                    <td>{{ $sale->customer?->name ?? __('Walk-in Customer') }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ $sale->items_count }} items</span></td>
                                    <td><span class="badge bg-secondary text-capitalize">{{ $sale->payment_method }}</span></td>
                                    <td class="text-end fw-extrabold text-primary">Rs {{ number_format($sale->total, 2) }}</td>
                                    <td class="pe-4 text-end">
                                        <a href="{{ route('cashier.billing.receipt', $sale) }}" class="btn btn-outline-secondary btn-sm rounded-circle" target="_blank" title="{{ __('Print Receipt') }}" style="width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-0">
                                        <x-empty-state icon="bi-receipt-cutoff" :title="__('No sales yet today')" :text="__('Completed bills will appear here as you check customers out.')" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Last 10 Transactions -->
            <div class="card border-0 shadow-sm" id="recent-transactions">
                <div class="card-header bg-white py-3 border-bottom">
                    <span class="fw-bold text-dark h5 mb-0"><i class="bi bi-clock-history me-1.5 text-primary"></i> {{ __('Last 10 System Transactions') }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">{{ __('Invoice') }}</th>
                                <th>{{ __('Date & Time') }}</th>
                                <th>{{ __('Customer') }}</th>
                                <th class="text-end">{{ __('Total') }}</th>
                                <th class="pe-4 text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentTransactions as $sale)
                                <tr>
                                    <td class="ps-4 font-monospace small fw-bold text-dark">{{ $sale->invoice_no }}</td>
                                    <td>{{ $sale->created_at->format('Y-m-d h:i A') }}</td>
                                    <td>{{ $sale->customer?->name ?? __('Walk-in Customer') }}</td>
                                    <td class="text-end fw-bold text-dark">Rs {{ number_format($sale->total, 2) }}</td>
                                    <td class="pe-4 text-end">
                                        <a href="{{ route('cashier.billing.receipt', $sale) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3" target="_blank">
                                            <i class="bi bi-printer me-1"></i> {{ __('Reprint') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-0"><x-empty-state icon="bi-clock-history" :title="__('No transactions yet')" /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column: Payment Breakdown & Quick Tools (col-lg-4) -->
        <div class="col-lg-4 d-flex flex-column gap-4">
            <!-- Payment Summary -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom fw-bold text-dark">
                    <i class="bi bi-wallet2 me-1.5 text-primary"></i> {{ __('Payment Summary (Today)') }}
                </div>
                <div class="card-body p-4">
                    @php $paymentMax = max(array_merge(array_values($paymentSummary), [1])); @endphp
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small fw-bold mb-1">
                            <span><i class="bi bi-cash-coin me-1 text-success"></i> {{ __('Cash') }}</span>
                            <span class="text-dark">Rs {{ number_format($paymentSummary['cash'], 2) }}</span>
                        </div>
                        <div class="progress rounded-pill" style="height:8px;">
                            <div class="progress-bar bg-success rounded-pill" style="width: {{ $paymentSummary['cash'] / $paymentMax * 100 }}%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small fw-bold mb-1">
                            <span><i class="bi bi-credit-card me-1 text-primary"></i> {{ __('Card') }}</span>
                            <span class="text-dark">Rs {{ number_format($paymentSummary['card'], 2) }}</span>
                        </div>
                        <div class="progress rounded-pill" style="height:8px;">
                            <div class="progress-bar bg-primary rounded-pill" style="width: {{ $paymentSummary['card'] / $paymentMax * 100 }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between small fw-bold mb-1">
                            <span><i class="bi bi-qr-code me-1 text-info"></i> {{ __('Other / Digital') }}</span>
                            <span class="text-dark">Rs {{ number_format($paymentSummary['other'], 2) }}</span>
                        </div>
                        <div class="progress rounded-pill" style="height:8px;">
                            <div class="progress-bar bg-info rounded-pill" style="width: {{ $paymentSummary['other'] / $paymentMax * 100 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Low Stock Warning Widget -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark"><i class="bi bi-exclamation-triangle me-1.5 text-warning"></i> {{ __('Low Stock Warnings') }}</span>
                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill">{{ $lowStock->count() }}</span>
                </div>
                <div class="list-group list-group-flush overflow-auto" style="max-height: 220px;">
                    @forelse ($lowStock as $product)
                        <div class="list-group-item py-2.5 px-3">
                            <div class="d-flex justify-content-between small fw-bold">
                                <span class="text-dark">{{ $product->name }}</span>
                                <span class="badge {{ $product->stock_qty == 0 ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning-emphasis' }} rounded-pill">
                                    {{ $product->stock_qty }} {{ $product->unit }}
                                </span>
                            </div>
                            <div class="progress mt-1.5 rounded-pill" style="height:5px;">
                                <div class="progress-bar {{ $product->stock_qty == 0 ? 'bg-danger' : 'bg-warning' }}"
                                     style="width: {{ $product->reorder_level > 0 ? min(100, $product->stock_qty / $product->reorder_level * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-center text-muted small py-4">{{ __('All stock levels are healthy.') }}</div>
                    @endforelse
                </div>
            </div>

            <!-- AI Quick Ask Widget -->
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #EEF2FF 0%, #FFFFFF 100%);">
                <div class="card-header bg-transparent py-3 border-bottom border-primary-subtle fw-bold text-primary">
                    <i class="bi bi-stars me-1.5 text-gold"></i> {{ __('AI Quick Business Assistant') }}
                </div>
                <div class="card-body p-3">
                    <form method="POST" action="{{ route('cashier.ai-chat.ask') }}">
                        @csrf
                        <textarea name="message" class="form-control form-control-sm mb-2 rounded-3" rows="2" placeholder="{{ __('e.g. How many bags of rice are in stock?') }}" required>{{ old('message') }}</textarea>
                        <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold rounded-2">{{ __('Ask AI') }}</button>
                    </form>

                    @if (! $geminiConfigured)
                        <div class="alert alert-light border small mt-2 mb-0 py-2 text-muted">{{ __('AI answers are unavailable until API key is configured.') }}</div>
                    @endif

                    @if ($aiLogs->isNotEmpty())
                        <div class="mt-3 small overflow-auto" style="max-height: 160px;">
                            @foreach ($aiLogs as $log)
                                <div class="mb-2 pb-2 border-bottom">
                                    <div class="fw-bold text-dark">{{ $log->query }}</div>
                                    <div class="text-muted small">{{ $log->response }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="mt-2 text-end">
                        <a href="{{ route('cashier.ai-chat.index') }}" class="small fw-bold text-primary">{{ __('Open full assistant') }} &rarr;</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateClock() {
            const el = document.getElementById('live-clock');
            if (!el) return;
            el.textContent = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        }
        setInterval(updateClock, 1000);

        function wireQuickSearch(inputId, resultsId, url, renderItem) {
            const input = document.getElementById(inputId);
            const results = document.getElementById(resultsId);
            let debounce;
            input.addEventListener('input', (e) => {
                clearTimeout(debounce);
                const term = e.target.value.trim();
                if (!term) { results.innerHTML = ''; return; }
                debounce = setTimeout(() => {
                    fetch(`${url}?q=${encodeURIComponent(term)}`)
                        .then(r => r.json())
                        .then(items => {
                            results.innerHTML = items.map(renderItem).join('') || '<div class="list-group-item small text-muted">{{ __('No matches found.') }}</div>';
                        });
                }, 250);
            });
        }

        wireQuickSearch('quick-product-input', 'quick-product-results', '{{ route('products.search') }}',
            (p) => `<div class="list-group-item d-flex justify-content-between align-items-center small py-2"><span><strong class="text-dark">${p.name}</strong> <span class="text-muted">(${p.sku})</span></span><span class="fw-bold text-primary">Rs ${parseFloat(p.selling_price).toFixed(2)} &middot; <span class="badge bg-light text-dark">${p.stock_qty} in stock</span></span></div>`);

        wireQuickSearch('quick-customer-input', 'quick-customer-results', '{{ route('customers.search') }}',
            (c) => `<div class="list-group-item small py-2"><strong class="text-dark">${c.name}</strong> ${c.phone ? ' &middot; ' + c.phone : ''} <span class="badge bg-gold-subtle ms-2"><i class="bi bi-star-fill text-gold me-1"></i> ${c.points_balance} pts</span></div>`);
    </script>
</x-cashier-layout>
