@php
    $promotion = $promotion ?? null;
    $productPrices = $products->pluck('selling_price', 'id');
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold text-dark border-bottom py-3">
                <i class="bi bi-megaphone me-1.5 text-primary"></i> {{ __('Promotion Details') }}
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <x-input-label for="title" :value="__('Promotion Title')" />
                    <x-text-input id="title" name="title" type="text" :value="old('title', $promotion?->title)" placeholder="{{ __('e.g. Weekend Rice Sale') }}" required autofocus />
                    <x-input-error :messages="$errors->get('title')" />
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <x-input-label for="product_id" :value="__('Product')" />
                        <select id="product_id" name="product_id" class="form-select" required>
                            <option value="">{{ __('Select a product…') }}</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" data-price="{{ $product->selling_price }}" @selected(old('product_id', $promotion?->product_id) == $product->id)>{{ $product->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('product_id')" />
                    </div>
                    <div class="col-md-6 mb-3">
                        <x-input-label for="current_price_display" :value="__('Current Price')" />
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted">Rs</span>
                            <input type="text" id="current_price_display" class="form-control fw-bold" value="{{ old('offer_price') ? '' : number_format($promotion?->current_price ?? 0, 2) }}" readonly>
                        </div>
                        <div class="form-text">{{ __('Auto-loaded from the selected product.') }}</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <x-input-label for="offer_price" :value="__('Offer Price')" />
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted">Rs</span>
                            <x-text-input id="offer_price" name="offer_price" type="number" step="0.01" min="0" :value="old('offer_price', $promotion?->offer_price)" required />
                        </div>
                        <x-input-error :messages="$errors->get('offer_price')" />
                    </div>
                    <div class="col-md-6 mb-3">
                        <x-input-label for="discount_percentage_display" :value="__('Discount %')" />
                        <div class="input-group">
                            <input type="text" id="discount_percentage_display" class="form-control fw-bold text-success" value="{{ $promotion?->discount_percentage ?? 0 }}" readonly>
                            <span class="input-group-text bg-white text-muted">%</span>
                        </div>
                        <div class="form-text">{{ __('Calculated automatically from current price − offer price.') }}</div>
                    </div>
                </div>

                <div class="mb-3">
                    <x-input-label for="description" :value="__('Promotion Description')" />
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="{{ __('Optional — a short line shown alongside the poster.') }}">{{ old('description', $promotion?->description) }}</textarea>
                    <x-input-error :messages="$errors->get('description')" />
                </div>

                <div class="mb-1">
                    <x-input-label for="poster_image" :value="__('Promotion Poster')" />
                    @if ($promotion?->poster_path)
                        <div class="mb-2">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($promotion->poster_path) }}" alt="{{ __('Current poster') }}" class="rounded-3 border" style="max-height:120px;">
                            <span class="badge bg-secondary-subtle text-secondary ms-2">{{ $promotion->poster_source === 'ai' ? __('AI Generated') : __('Custom Upload') }}</span>
                        </div>
                    @endif
                    <input type="file" id="poster_image" name="poster_image" class="form-control" accept="image/png,image/jpeg,image/webp">
                    <div class="form-text">{{ __('PNG, JPG, or WEBP — max 10MB. AI poster generation is available after saving.') }}</div>
                    <x-input-error :messages="$errors->get('poster_image')" />
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold text-dark border-bottom py-3">
                <i class="bi bi-calendar-range me-1.5 text-primary"></i> {{ __('Schedule') }}
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <x-input-label for="start_date" :value="__('Start Date & Time')" />
                    <x-text-input id="start_date" name="start_date" type="datetime-local" :value="old('start_date', $promotion?->start_date?->format('Y-m-d\TH:i'))" required />
                    <x-input-error :messages="$errors->get('start_date')" />
                </div>
                <div class="mb-3">
                    <x-input-label for="end_date" :value="__('End Date & Time')" />
                    <x-text-input id="end_date" name="end_date" type="datetime-local" :value="old('end_date', $promotion?->end_date?->format('Y-m-d\TH:i'))" required />
                    <x-input-error :messages="$errors->get('end_date')" />
                </div>
                <div class="mb-1">
                    <x-input-label for="display_duration" :value="__('Display Duration (seconds)')" />
                    <x-text-input id="display_duration" name="display_duration" type="number" min="5" max="300" placeholder="10" :value="old('display_duration', $promotion?->display_duration ?? 10)" required />
                    <div class="form-text">{{ __('How many seconds this promotion should remain visible on the Customer Display.') }}</div>
                    <x-input-error :messages="$errors->get('display_duration')" />
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold text-dark border-bottom py-3">
                <i class="bi bi-sliders me-1.5 text-primary"></i> {{ __('Display Settings') }}
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <x-input-label for="priority" :value="__('Priority')" />
                    <select id="priority" name="priority" class="form-select">
                        @foreach (['high' => __('High'), 'normal' => __('Normal'), 'low' => __('Low')] as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', $promotion?->priority ?? 'normal') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <x-input-label for="target_screen" :value="__('Target Screen')" />
                    <select id="target_screen" name="target_screen" class="form-select">
                        <option value="customer_display" @selected(old('target_screen', $promotion?->target_screen ?? 'customer_display') === 'customer_display')>{{ __('Customer Display') }}</option>
                        <option value="dashboard_banner" @selected(old('target_screen', $promotion?->target_screen) === 'dashboard_banner')>{{ __('Dashboard Banner') }}</option>
                        <option value="both" @selected(old('target_screen', $promotion?->target_screen) === 'both')>{{ __('Both') }}</option>
                    </select>
                </div>
                <div class="form-check form-switch p-2 bg-light rounded-2 border ps-5">
                    <input type="checkbox" name="is_featured" value="1" class="form-check-input" id="is_featured" style="margin-left:-2.5em;" @checked(old('is_featured', $promotion?->is_featured))>
                    <label class="form-check-label fw-semibold text-dark small" for="is_featured">{{ __('Featured (appears more frequently in rotation)') }}</label>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const productSelect = document.getElementById('product_id');
        const currentPriceDisplay = document.getElementById('current_price_display');
        const offerPriceInput = document.getElementById('offer_price');
        const discountDisplay = document.getElementById('discount_percentage_display');

        function currentPrice() {
            const option = productSelect.options[productSelect.selectedIndex];
            return option && option.dataset.price ? parseFloat(option.dataset.price) : 0;
        }

        function recalc() {
            const price = currentPrice();
            currentPriceDisplay.value = price ? price.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
            offerPriceInput.max = price || null;

            const offer = parseFloat(offerPriceInput.value) || 0;
            const discount = price > 0 ? ((price - offer) / price) * 100 : 0;
            discountDisplay.value = discount.toFixed(1);
        }

        productSelect.addEventListener('change', recalc);
        offerPriceInput.addEventListener('input', recalc);
        recalc();
    })();
</script>
