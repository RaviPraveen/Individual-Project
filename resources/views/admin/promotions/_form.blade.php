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
                    <div class="form-text">{{ $promotion ? __('PNG, JPG, or WEBP — max 10MB.') : __('PNG, JPG, or WEBP — max 10MB. Or generate one with AI below.') }}</div>
                    <x-input-error :messages="$errors->get('poster_image')" />
                </div>
            </div>
        </div>

        @if (! $promotion)
            {{-- Create-only: the Edit page's AI Poster Generator (edit.blade.php)
                 works against an already-saved Promotion; here nothing is
                 saved yet, so generation stashes the result in the session
                 (see PromotionPosterController::generateDraft()) and
                 "Use This Poster" just flags a hidden field — the actual
                 file only gets attached to a real promotion on submit. --}}
            <div class="card border-0 shadow-sm mb-3" id="ai-poster-generator" style="border: 1px solid #DBEAFE !important;">
                <div class="card-header bg-white fw-bold text-dark border-bottom py-3">
                    <i class="bi bi-stars text-warning me-1.5"></i> {{ __('AI Promotion Poster Generator') }}
                </div>
                <div class="card-body">
                    <input type="hidden" name="use_generated_poster" id="use_generated_poster" value="{{ old('use_generated_poster') ? '1' : '0' }}">

                    <div id="ai-poster-preview-wrap" class="rounded-3 border border-2 border-primary-subtle bg-light d-flex align-items-center justify-content-center overflow-hidden position-relative mb-3" style="aspect-ratio:16/9; max-width:520px;">
                        <div id="ai-poster-skeleton" class="w-100 h-100 d-none" style="background: linear-gradient(90deg, #E2E8F0 25%, #F1F5F9 50%, #E2E8F0 75%); background-size: 200% 100%; animation: pos-skeleton 1.2s ease-in-out infinite;"></div>
                        @if ($draftPoster ?? null)
                            <img id="ai-poster-preview-img" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($draftPoster['path']) }}" class="w-100 h-100" style="object-fit:cover;" alt="{{ __('Generated poster preview') }}">
                        @else
                            <div class="text-muted text-center p-4" id="ai-poster-preview-empty"><i class="bi bi-magic fs-1 d-block mb-2"></i>{{ __('Fill in Product, Offer Price, and Title above, then generate a poster') }}</div>
                        @endif
                    </div>

                    <div id="ai-poster-note" class="small text-muted mb-2 {{ (($draftPoster['used_ai'] ?? true)) ? 'd-none' : '' }}">
                        {{ __('AI image service was unavailable for the last attempt — this is a placeholder background. Try Generate Again, or use it anyway.') }}
                    </div>

                    <div id="ai-poster-selected-note" class="small fw-semibold text-success mb-2 {{ old('use_generated_poster') ? '' : 'd-none' }}">
                        <i class="bi bi-check-circle-fill me-1"></i> {{ __('This poster will be used for the new promotion.') }}
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" id="ai-btn-generate" class="btn btn-primary fw-bold px-3">
                            <i class="bi bi-magic me-1"></i> <span id="ai-btn-generate-label">{{ ($draftPoster ?? null) ? __('Generate Again') : __('Generate AI Poster') }}</span>
                        </button>
                        <button type="button" id="ai-btn-use" class="btn btn-success fw-bold px-3 {{ ($draftPoster ?? null) ? '' : 'd-none' }}">
                            <i class="bi bi-check-circle me-1"></i> {{ __('Use This Poster') }}
                        </button>
                        <a href="{{ ($draftPoster ?? null) ? \Illuminate\Support\Facades\Storage::disk('public')->url($draftPoster['path']) : '#' }}" download id="ai-btn-download" class="btn btn-outline-secondary px-3 {{ ($draftPoster ?? null) ? '' : 'd-none' }}">
                            <i class="bi bi-download me-1"></i> {{ __('Download') }}
                        </a>
                        <button type="button" id="ai-btn-remove" class="btn btn-outline-danger px-3 {{ ($draftPoster ?? null) ? '' : 'd-none' }}">
                            <i class="bi bi-trash3 me-1"></i> {{ __('Remove') }}
                        </button>
                    </div>
                </div>
            </div>
        @endif
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

    // Create-only AI Poster Generator — a no-op on the Edit page, where
    // #ai-poster-generator doesn't exist (it has its own separate,
    // already-saved-promotion-backed generator in edit.blade.php).
    if (document.getElementById('ai-poster-generator')) {
        (function () {
            const generateDraftUrl = '{{ route('admin.promotions.poster.generate-draft') }}';
            const discardDraftUrl = '{{ route('admin.promotions.poster.discard-draft') }}';
            const csrf = document.querySelector('meta[name="csrf-token"]').content;

            const titleInput = document.getElementById('title');
            const productSelectEl = document.getElementById('product_id');
            const offerPriceInputEl = document.getElementById('offer_price');
            const descriptionInput = document.getElementById('description');
            const useGeneratedField = document.getElementById('use_generated_poster');

            const btnGenerate = document.getElementById('ai-btn-generate');
            const btnGenerateLabel = document.getElementById('ai-btn-generate-label');
            const btnUse = document.getElementById('ai-btn-use');
            const btnDownload = document.getElementById('ai-btn-download');
            const btnRemove = document.getElementById('ai-btn-remove');
            const skeleton = document.getElementById('ai-poster-skeleton');
            const previewWrap = document.getElementById('ai-poster-preview-wrap');
            const note = document.getElementById('ai-poster-note');
            const selectedNote = document.getElementById('ai-poster-selected-note');

            function setPreviewImage(url) {
                document.getElementById('ai-poster-preview-empty')?.remove();
                let img = document.getElementById('ai-poster-preview-img');
                if (!img) {
                    img = document.createElement('img');
                    img.id = 'ai-poster-preview-img';
                    img.className = 'w-100 h-100';
                    img.style.objectFit = 'cover';
                    img.alt = '{{ __('Generated poster preview') }}';
                    previewWrap.appendChild(img);
                }
                img.src = url + '?t=' + Date.now();
                btnDownload.href = url;
                btnDownload.classList.remove('d-none');
            }

            function markUnselected() {
                useGeneratedField.value = '0';
                selectedNote.classList.add('d-none');
            }

            btnGenerate.addEventListener('click', function () {
                if (!productSelectEl.value || !titleInput.value.trim() || !offerPriceInputEl.value) {
                    window.posToast ? window.posToast('{{ __('Fill in Product, Promotion Title, and Offer Price first.') }}', 'warning') : null;
                    return;
                }

                btnGenerate.disabled = true;
                skeleton.classList.remove('d-none');
                document.getElementById('ai-poster-preview-img')?.classList.add('d-none');
                markUnselected();

                fetch(generateDraftUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({
                        product_id: productSelectEl.value,
                        title: titleInput.value.trim(),
                        offer_price: offerPriceInputEl.value,
                        description: descriptionInput.value.trim(),
                    }),
                }).then(r => r.json().then(data => ({ ok: r.ok, data }))).then(({ ok, data }) => {
                    skeleton.classList.add('d-none');
                    document.getElementById('ai-poster-preview-img')?.classList.remove('d-none');
                    btnGenerate.disabled = false;

                    if (!ok) {
                        const message = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || '{{ __('Unable to generate poster. Please try again.') }}');
                        window.posToast ? window.posToast(message, 'danger') : null;
                        return;
                    }

                    setPreviewImage(data.poster_url);
                    btnGenerateLabel.textContent = '{{ __('Generate Again') }}';
                    btnUse.classList.remove('d-none');
                    btnRemove.classList.remove('d-none');
                    note.classList.toggle('d-none', data.used_ai);
                    window.posToast ? window.posToast(data.message, data.used_ai ? 'success' : 'warning') : null;
                }).catch(() => {
                    skeleton.classList.add('d-none');
                    btnGenerate.disabled = false;
                    window.posToast ? window.posToast('{{ __('Unable to generate poster. Please try again.') }}', 'danger') : null;
                });
            });

            btnUse.addEventListener('click', function () {
                useGeneratedField.value = '1';
                selectedNote.classList.remove('d-none');
                window.posToast ? window.posToast('{{ __('This poster will be used when you create the promotion.') }}', 'success') : null;
            });

            btnRemove.addEventListener('click', function () {
                fetch(discardDraftUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                }).then(() => {
                    document.getElementById('ai-poster-preview-img')?.remove();
                    previewWrap.insertAdjacentHTML('beforeend', '<div class="text-muted text-center p-4" id="ai-poster-preview-empty"><i class="bi bi-magic fs-1 d-block mb-2"></i>{{ __('Fill in Product, Offer Price, and Title above, then generate a poster') }}</div>');
                    btnUse.classList.add('d-none');
                    btnDownload.classList.add('d-none');
                    btnRemove.classList.add('d-none');
                    btnGenerateLabel.textContent = '{{ __('Generate AI Poster') }}';
                    markUnselected();
                    window.posToast ? window.posToast('{{ __('Generated poster discarded.') }}', 'success') : null;
                });
            });
        })();
    }
</script>

<style>
    @keyframes pos-skeleton {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
</style>
