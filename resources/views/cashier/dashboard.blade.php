<x-cashier-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h2 class="h4 mb-0">{{ __('Welcome, :name', ['name' => explode(' ', $cashier->name)[0]]) }}</h2>
                <div class="text-muted small">{{ $shift }} &middot; {{ now()->format('l, F j, Y') }}</div>
            </div>
            <div class="text-end">
                <div class="fs-4 fw-semibold font-monospace" id="live-clock">{{ now()->format('h:i:s A') }}</div>
                <div class="text-muted small">{{ __('Cashier Terminal') }}</div>
            </div>
        </div>
    </x-slot>

    {{-- Stat cards --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <x-stat-card icon="bi-cash-stack" tone="success" :label="__(\"Today's Sales\")" :value="number_format($stats['sales_total'], 2)" />
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

    {{-- Quick actions --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <a href="{{ route('cashier.billing.index') }}" class="card h-100 text-decoration-none border-0 shadow-sm quick-action">
                <div class="card-body text-center py-4">
                    <i class="bi bi-plus-circle fs-2 text-primary"></i>
                    <div class="fw-semibold mt-2">{{ __('New Billing') }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="#" class="card h-100 text-decoration-none border-0 shadow-sm quick-action" data-bs-toggle="collapse" data-bs-target="#quickProductSearch" role="button">
                <div class="card-body text-center py-4">
                    <i class="bi bi-search fs-2 text-success"></i>
                    <div class="fw-semibold mt-2">{{ __('Product Search') }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="#" class="card h-100 text-decoration-none border-0 shadow-sm quick-action" data-bs-toggle="collapse" data-bs-target="#quickCustomerSearch" role="button">
                <div class="card-body text-center py-4">
                    <i class="bi bi-person-lines-fill fs-2 text-info"></i>
                    <div class="fw-semibold mt-2">{{ __('Customer Search') }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="#recent-transactions" class="card h-100 text-decoration-none border-0 shadow-sm quick-action">
                <div class="card-body text-center py-4">
                    <i class="bi bi-clock-history fs-2 text-secondary"></i>
                    <div class="fw-semibold mt-2">{{ __('Recent Bills') }}</div>
                </div>
            </a>
        </div>
    </div>

    <div class="collapse mb-3" id="quickProductSearch">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <label class="form-label small text-muted">{{ __('Quick product lookup (name, SKU, or barcode)') }}</label>
                <input type="text" id="quick-product-input" class="form-control" placeholder="{{ __('e.g. Rice 5kg') }}">
                <div id="quick-product-results" class="list-group mt-2"></div>
            </div>
        </div>
    </div>

    <div class="collapse mb-3" id="quickCustomerSearch">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <label class="form-label small text-muted">{{ __('Quick customer lookup (name or phone)') }}</label>
                <input type="text" id="quick-customer-input" class="form-control" placeholder="{{ __('e.g. Nimal Perera') }}">
                <div id="quick-customer-results" class="list-group mt-2"></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Left column --}}
        <div class="col-lg-8 d-flex flex-column gap-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-journal-text me-1"></i> {{ __("My Today's Sales") }}</span>
                    <span class="badge text-bg-light">{{ $todaysSales->count() }} {{ __('bills') }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Invoice') }}</th>
                                <th>{{ __('Time') }}</th>
                                <th>{{ __('Customer') }}</th>
                                <th>{{ __('Items') }}</th>
                                <th>{{ __('Payment') }}</th>
                                <th class="text-end">{{ __('Total') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($todaysSales as $sale)
                                <tr>
                                    <td class="font-monospace small">{{ $sale->invoice_no }}</td>
                                    <td>{{ $sale->created_at->format('h:i A') }}</td>
                                    <td>{{ $sale->customer?->name ?? __('Walk-in') }}</td>
                                    <td>{{ $sale->items_count }}</td>
                                    <td><span class="badge text-bg-secondary text-capitalize">{{ $sale->payment_method }}</span></td>
                                    <td class="text-end fw-semibold">{{ number_format($sale->total, 2) }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('cashier.billing.receipt', $sale) }}" class="btn btn-outline-secondary btn-sm" target="_blank">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-0"><x-empty-state icon="bi-receipt-cutoff" :title="__('No sales yet today')" :text="__('Completed bills will appear here as you check customers out.')" /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card border-0 shadow-sm" id="recent-transactions">
                <div class="card-header bg-white">
                    <span class="fw-semibold"><i class="bi bi-clock-history me-1"></i> {{ __('Last 10 Transactions') }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Invoice') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Customer') }}</th>
                                <th class="text-end">{{ __('Total') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentTransactions as $sale)
                                <tr>
                                    <td class="font-monospace small">{{ $sale->invoice_no }}</td>
                                    <td>{{ $sale->created_at->format('Y-m-d h:i A') }}</td>
                                    <td>{{ $sale->customer?->name ?? __('Walk-in') }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($sale->total, 2) }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('cashier.billing.receipt', $sale) }}" class="btn btn-outline-secondary btn-sm" target="_blank">
                                            {{ __('Reprint') }}
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

        {{-- Right column --}}
        <div class="col-lg-4 d-flex flex-column gap-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-wallet2 me-1"></i> {{ __('Payment Summary (Today)') }}</div>
                <div class="card-body">
                    @php $paymentMax = max(array_merge(array_values($paymentSummary), [1])); @endphp
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><i class="bi bi-cash-coin me-1"></i>{{ __('Cash') }}</span>
                            <span class="fw-semibold">{{ number_format($paymentSummary['cash'], 2) }}</span>
                        </div>
                        <div class="progress" style="height:6px;">
                            <div class="progress-bar bg-success" style="width: {{ $paymentSummary['cash'] / $paymentMax * 100 }}%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><i class="bi bi-credit-card me-1"></i>{{ __('Card') }}</span>
                            <span class="fw-semibold">{{ number_format($paymentSummary['card'], 2) }}</span>
                        </div>
                        <div class="progress" style="height:6px;">
                            <div class="progress-bar bg-primary" style="width: {{ $paymentSummary['card'] / $paymentMax * 100 }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span><i class="bi bi-three-dots me-1"></i>{{ __('Other') }}</span>
                            <span class="fw-semibold">{{ number_format($paymentSummary['other'], 2) }}</span>
                        </div>
                        <div class="progress" style="height:6px;">
                            <div class="progress-bar bg-secondary" style="width: {{ $paymentSummary['other'] / $paymentMax * 100 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-exclamation-triangle me-1 text-warning"></i> {{ __('Low Stock Alerts') }}</span>
                    <span class="badge text-bg-warning">{{ $lowStock->count() }}</span>
                </div>
                <div class="list-group list-group-flush" style="max-height: 220px; overflow-y: auto;">
                    @forelse ($lowStock as $product)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between small">
                                <span>{{ $product->name }}</span>
                                <span class="badge {{ $product->stock_qty == 0 ? 'text-bg-danger' : 'text-bg-warning' }}">
                                    {{ $product->stock_qty }} {{ $product->unit }}
                                </span>
                            </div>
                            <div class="progress mt-1" style="height:4px;">
                                <div class="progress-bar {{ $product->stock_qty == 0 ? 'bg-danger' : 'bg-warning' }}"
                                     style="width: {{ $product->reorder_level > 0 ? min(100, $product->stock_qty / $product->reorder_level * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-center text-muted small py-3">{{ __('All stock levels are healthy.') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-bell me-1"></i> {{ __('Notifications') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse ($notices as $notice)
                        <li class="list-group-item small"><i class="bi bi-dot"></i> {{ $notice }}</li>
                    @empty
                        <li class="list-group-item small text-muted">{{ __('Nothing new right now.') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-robot me-1"></i> {{ __('AI Business Assistant') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('cashier.ai-chat.ask') }}">
                        @csrf
                        <textarea name="message" class="form-control form-control-sm mb-2" rows="2" placeholder="{{ __('e.g. How many bags of rice are in stock?') }}" required>{{ old('message') }}</textarea>
                        <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('Ask') }}</button>
                    </form>

                    @if (! $geminiConfigured)
                        <div class="alert alert-secondary small mt-2 mb-0 py-2">{{ __('AI answers are unavailable until an API key is configured.') }}</div>
                    @endif

                    @if ($aiLogs->isNotEmpty())
                        <div class="mt-3 small" style="max-height: 160px; overflow-y: auto;">
                            @foreach ($aiLogs as $log)
                                <div class="mb-2 pb-2 border-bottom">
                                    <div class="fw-semibold">{{ $log->query }}</div>
                                    <div class="text-muted">{{ $log->response }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <a href="{{ route('cashier.ai-chat.index') }}" class="small">{{ __('Open full assistant') }} &rarr;</a>
                </div>
            </div>
        </div>
    </div>

    <style>
        .quick-action { transition: transform .1s ease, box-shadow .1s ease; cursor: pointer; }
        .quick-action:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.1) !important; }
    </style>

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
                            results.innerHTML = items.map(renderItem).join('') || '<div class="list-group-item small text-muted">{{ __('No matches.') }}</div>';
                        });
                }, 250);
            });
        }

        wireQuickSearch('quick-product-input', 'quick-product-results', '{{ route('products.search') }}',
            (p) => `<div class="list-group-item d-flex justify-content-between small"><span>${p.name} <span class="text-muted">(${p.sku})</span></span><span>${p.stock_qty} {{ __('in stock') }} &middot; ${parseFloat(p.selling_price).toFixed(2)}</span></div>`);

        wireQuickSearch('quick-customer-input', 'quick-customer-results', '{{ route('customers.search') }}',
            (c) => `<div class="list-group-item small">${c.name}${c.phone ? ' &middot; ' + c.phone : ''}</div>`);
    </script>
</x-cashier-layout>
