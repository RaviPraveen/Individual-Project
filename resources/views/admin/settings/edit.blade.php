<x-admin-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Store Settings') }}</h2>
    </x-slot>

    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
                        <span class="icon-badge bg-danger-subtle text-danger" style="width:32px;height:32px;font-size:1rem;"><i class="bi bi-receipt-cutoff"></i></span>
                        {{ __('Sales Tax') }}
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">{{ __('Flat tax percentage applied to every sale after discount. Takes effect immediately for new bills and returns.') }}</p>
                        <x-input-label for="tax_rate" :value="__('Tax rate')" />
                        <div class="input-group">
                            <input type="number" name="tax_rate" id="tax_rate" class="form-control" min="0" max="100" step="0.01" value="{{ old('tax_rate', $taxRate) }}" required>
                            <span class="input-group-text">%</span>
                        </div>
                        <x-input-error :messages="$errors->get('tax_rate')" />
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
                        <span class="icon-badge bg-success-subtle text-success" style="width:32px;height:32px;font-size:1rem;"><i class="bi bi-currency-exchange"></i></span>
                        {{ __('Currency') }}
                    </div>
                    <div class="card-body">
                        <x-input-label for="currency_symbol" :value="__('Currency symbol')" />
                        <x-text-input id="currency_symbol" name="currency_symbol" type="text" :value="old('currency_symbol', $currencySymbol)" required />
                        <x-input-error :messages="$errors->get('currency_symbol')" />
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
                        <span class="icon-badge bg-warning-subtle text-warning" style="width:32px;height:32px;font-size:1rem;"><i class="bi bi-box-seam"></i></span>
                        {{ __('Inventory') }}
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">{{ __('Default reorder level pre-filled when adding a new product. Existing products are unaffected.') }}</p>
                        <x-input-label for="low_stock_threshold_default" :value="__('Default low-stock threshold')" />
                        <x-text-input id="low_stock_threshold_default" name="low_stock_threshold_default" type="number" min="0" :value="old('low_stock_threshold_default', $lowStockThresholdDefault)" required />
                        <x-input-error :messages="$errors->get('low_stock_threshold_default')" />
                    </div>
                </div>

                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> {{ __('About These Settings') }}</div>
                <div class="card-body">
                    <p class="text-muted small mb-2">{{ __('These values are read live by the billing and reporting screens — no deployment needed after saving.') }}</p>
                    <ul class="text-muted small mb-0 ps-3">
                        <li>{{ __('Tax rate is applied to every sale and refund.') }}</li>
                        <li>{{ __('Currency symbol is used across receipts and reports.') }}</li>
                        <li>{{ __('Low-stock threshold only pre-fills the form for new products.') }}</li>
                    </ul>
                    <hr>
                    <p class="text-muted small mb-0">
                        {{ __('Looking for store name, address, or receipt footer? Those live under') }}
                        <a href="{{ route('admin.receipt-settings.edit') }}">{{ __('Receipt Designer') }}</a>.
                        {{ __('Loyalty rates and bag fee are under') }}
                        <a href="{{ route('admin.billing-settings.edit') }}">{{ __('Billing Settings') }}</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
