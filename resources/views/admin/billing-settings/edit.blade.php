<x-admin-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Billing Settings') }}</h2>
    </x-slot>

    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('admin.billing-settings.update') }}">
                @csrf
                @method('PUT')

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
                        <span class="icon-badge bg-warning-subtle text-warning" style="width:32px;height:32px;font-size:1rem;"><i class="bi bi-star-fill"></i></span>
                        {{ __('Star Points — Earning') }}
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">{{ __('What percentage of the bill total should be added as points? e.g. enter 10 for 10%.') }}</p>
                        <x-input-label for="points_earn_percent" :value="__('% of bill earned as points')" />
                        <div class="input-group">
                            <input type="number" name="points_earn_percent" id="points_earn_percent" class="form-control bs-field" min="0" max="100" step="0.001" value="{{ old('points_earn_percent', $settings->points_earn_percent) }}" required>
                            <span class="input-group-text">%</span>
                        </div>
                        <x-input-error :messages="$errors->get('points_earn_percent')" />
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
                        <span class="icon-badge bg-success-subtle text-success" style="width:32px;height:32px;font-size:1rem;"><i class="bi bi-cash-coin"></i></span>
                        {{ __('Star Points — Redeeming') }}
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">{{ __('When a customer spends their points at checkout, how much is 1 point worth?') }}</p>
                        <x-input-label for="points_redeem_value" :value="__('Rupee value of 1 redeemed point')" />
                        <div class="input-group">
                            <span class="input-group-text">Rs</span>
                            <input type="number" name="points_redeem_value" id="points_redeem_value" class="form-control bs-field" min="0" step="0.01" value="{{ old('points_redeem_value', $settings->points_redeem_value) }}" required>
                        </div>
                        <x-input-error :messages="$errors->get('points_redeem_value')" />
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
                        <span class="icon-badge bg-info-subtle text-info" style="width:32px;height:32px;font-size:1rem;"><i class="bi bi-bag-fill"></i></span>
                        {{ __('Shopping Bag Fee') }}
                    </div>
                    <div class="card-body">
                        <x-input-label for="bag_fee" :value="__('Fee charged if the customer wants a bag')" />
                        <div class="input-group">
                            <span class="input-group-text">Rs</span>
                            <input type="number" name="bag_fee" id="bag_fee" class="form-control bs-field" min="0" step="0.01" value="{{ old('bag_fee', $settings->bag_fee) }}" required>
                        </div>
                        <div class="form-text">{{ __('Set to 0 to disable the bag-fee prompt at checkout.') }}</div>
                        <x-input-error :messages="$errors->get('bag_fee')" />
                    </div>
                </div>

                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="card" style="position: sticky; top: 90px;">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt me-1"></i> {{ __('Example Bill') }}</div>
                <div class="card-body">
                    <p class="text-muted small mb-3">{{ __('Updates live as you edit the settings on the left.') }}</p>

                    <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                        <span class="text-muted">{{ __('Bill total') }}</span>
                        <span class="fw-semibold fs-5 num-tabular">Rs 1,000.00</span>
                    </div>

                    <div class="d-flex align-items-center gap-3 py-3 border-bottom">
                        <span class="icon-badge bg-warning-subtle text-warning"><i class="bi bi-star-fill"></i></span>
                        <div>
                            <div class="label">{{ __('Points Earned') }}</div>
                            <div class="value" id="preview-points-earned">0</div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-3 py-3 border-bottom">
                        <span class="icon-badge bg-success-subtle text-success"><i class="bi bi-cash-coin"></i></span>
                        <div>
                            <div class="label">{{ __('100 Points Redeemed = ') }}</div>
                            <div class="value" id="preview-redeem-value">Rs 0.00</div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-3 py-3">
                        <span class="icon-badge bg-info-subtle text-info"><i class="bi bi-bag-fill"></i></span>
                        <div>
                            <div class="label">{{ __('Bag Fee (if requested)') }}</div>
                            <div class="value" id="preview-bag-fee">Rs 0.00</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const earnPercentInput = document.getElementById('points_earn_percent');
        const redeemInput = document.getElementById('points_redeem_value');
        const bagFeeInput = document.getElementById('bag_fee');

        function renderPreview() {
            const percent = parseFloat(earnPercentInput.value) || 0;
            const redeem = parseFloat(redeemInput.value) || 0;
            const bagFee = parseFloat(bagFeeInput.value) || 0;
            const exampleBill = 1000;

            document.getElementById('preview-points-earned').textContent = Math.floor(exampleBill * (percent / 100));
            document.getElementById('preview-redeem-value').textContent = `Rs ${(100 * redeem).toFixed(2)}`;
            document.getElementById('preview-bag-fee').textContent = `Rs ${bagFee.toFixed(2)}`;
        }

        [earnPercentInput, redeemInput, bagFeeInput].forEach(el => el.addEventListener('input', renderPreview));
        renderPreview();
    </script>
</x-admin-layout>
