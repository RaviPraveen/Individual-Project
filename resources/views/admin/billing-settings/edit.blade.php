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
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-star me-1 text-gold"></i> {{ __('Star Points — Earning') }}</div>
                    <div class="card-body">
                        <p class="text-muted small">{{ __('Set this the way you think about it: "customers spend this much, they earn this many points."') }}</p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-input-label for="points_earn_amount" :value="__('Rs spent')" />
                                <input type="number" name="points_earn_amount" id="points_earn_amount" class="form-control bs-field" min="0.01" step="0.01" value="{{ old('points_earn_amount', $settings->points_earn_amount) }}" required>
                                <x-input-error :messages="$errors->get('points_earn_amount')" />
                            </div>
                            <div class="col-md-6">
                                <x-input-label for="points_earn_count" :value="__('Points earned')" />
                                <input type="number" name="points_earn_count" id="points_earn_count" class="form-control bs-field" min="0" step="0.01" value="{{ old('points_earn_count', $settings->points_earn_count) }}" required>
                                <x-input-error :messages="$errors->get('points_earn_count')" />
                            </div>
                        </div>
                        <div class="alert alert-secondary mt-3 mb-0 small" id="earn-preview"></div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-coin me-1"></i> {{ __('Star Points — Redeeming') }}</div>
                    <div class="card-body">
                        <x-input-label for="points_redeem_value" :value="__('Rupee value of 1 redeemed point')" />
                        <input type="number" name="points_redeem_value" id="points_redeem_value" class="form-control bs-field" min="0" step="0.01" value="{{ old('points_redeem_value', $settings->points_redeem_value) }}" required>
                        <x-input-error :messages="$errors->get('points_redeem_value')" />
                        <div class="alert alert-secondary mt-3 mb-0 small" id="redeem-preview"></div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-bag me-1"></i> {{ __('Shopping Bag Fee') }}</div>
                    <div class="card-body">
                        <x-input-label for="bag_fee" :value="__('Fee charged if the customer wants a bag (Rs)')" />
                        <input type="number" name="bag_fee" id="bag_fee" class="form-control" min="0" step="0.01" value="{{ old('bag_fee', $settings->bag_fee) }}" required>
                        <div class="form-text">{{ __('Set to 0 to disable the bag-fee prompt at checkout.') }}</div>
                        <x-input-error :messages="$errors->get('bag_fee')" />
                    </div>
                </div>

                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
            </form>
        </div>
    </div>

    <script>
        const amountInput = document.getElementById('points_earn_amount');
        const countInput = document.getElementById('points_earn_count');
        const redeemInput = document.getElementById('points_redeem_value');
        const earnPreview = document.getElementById('earn-preview');
        const redeemPreview = document.getElementById('redeem-preview');

        function renderPreview() {
            const amount = parseFloat(amountInput.value) || 0;
            const count = parseFloat(countInput.value) || 0;
            const redeem = parseFloat(redeemInput.value) || 0;

            if (amount > 0) {
                const percent = (count / amount) * 100;
                const exampleBill = 1000;
                const examplePoints = Math.floor(exampleBill * (count / amount));
                earnPreview.textContent = `= ${percent.toFixed(3)}% earn rate — e.g. a Rs ${exampleBill.toLocaleString()} bill earns ~${examplePoints} point(s).`;
            } else {
                earnPreview.textContent = '{{ __('Enter an amount above 0.') }}';
            }

            redeemPreview.textContent = `{{ __('e.g. 100 points = Rs ') }}${(100 * redeem).toFixed(2)}`;
        }

        [amountInput, countInput, redeemInput].forEach(el => el.addEventListener('input', renderPreview));
        renderPreview();
    </script>
</x-admin-layout>
