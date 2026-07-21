@php $product = $product ?? null; @endphp

<div class="mb-3">
    <x-input-label for="name" :value="__('Product Title / Name')" />
    <x-text-input id="name" name="name" type="text" :value="old('name', $product?->name)" placeholder="{{ __('e.g. Fresh Whole Milk 1L') }}" required autofocus />
    <x-input-error :messages="$errors->get('name')" />
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <x-input-label for="sku" :value="__('SKU (Stock Keeping Unit)')" />
        <x-text-input id="sku" name="sku" type="text" :value="old('sku', $product?->sku)" placeholder="{{ __('e.g. MILK-001') }}" required />
        <x-input-error :messages="$errors->get('sku')" />
    </div>
    <div class="col-md-6 mb-3">
        <x-input-label for="barcode" :value="__('Barcode / EAN')" />
        <div class="input-group">
            <x-text-input id="barcode" name="barcode" type="text" :value="old('barcode', $product?->barcode)" placeholder="{{ __('Scan or enter barcode number...') }}" />
            <button type="button" class="btn btn-outline-primary" id="scan-barcode-btn" title="{{ __('Scan Barcode with Camera') }}" data-bs-toggle="tooltip">
                <i class="bi bi-camera-fill"></i>
            </button>
        </div>
        <x-input-error :messages="$errors->get('barcode')" />
    </div>
</div>

<div class="mb-3">
    <x-input-label for="category_id" :value="__('Product Category')" />
    <select id="category_id" name="category_id" class="form-select">
        <option value="">{{ __('None (Uncategorized)') }}</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('category_id', $product?->category_id) == $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('category_id')" />
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <x-input-label for="cost_price" :value="__('Cost Price (Rs)')" />
        <x-text-input id="cost_price" name="cost_price" type="number" step="0.01" min="0" :value="old('cost_price', $product?->cost_price)" placeholder="0.00" required />
        <x-input-error :messages="$errors->get('cost_price')" />
    </div>
    <div class="col-md-4 mb-3">
        <x-input-label for="selling_price" :value="__('Selling Price (Rs)')" />
        <x-text-input id="selling_price" name="selling_price" type="number" step="0.01" min="0" :value="old('selling_price', $product?->selling_price)" placeholder="0.00" required />
        <x-input-error :messages="$errors->get('selling_price')" />
    </div>
    <div class="col-md-4 mb-3">
        <x-input-label for="unit" :value="__('Measurement Unit')" />
        <x-text-input id="unit" name="unit" type="text" :value="old('unit', $product?->unit ?? 'pcs')" placeholder="{{ __('pcs, kg, packet, bottle...') }}" required />
        <x-input-error :messages="$errors->get('unit')" />
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <x-input-label for="stock_qty" :value="__('Current Stock Quantity')" />
        <x-text-input id="stock_qty" name="stock_qty" type="number" min="0" :value="old('stock_qty', $product?->stock_qty ?? 0)" required />
        <x-input-error :messages="$errors->get('stock_qty')" />
    </div>
    <div class="col-md-6 mb-3">
        <x-input-label for="reorder_level" :value="__('Reorder Threshold')" />
        <x-text-input id="reorder_level" name="reorder_level" type="number" min="0" :value="old('reorder_level', $product?->reorder_level ?? \App\Models\Setting::get('low_stock_threshold_default', 5))" required />
        <x-input-error :messages="$errors->get('reorder_level')" />
    </div>
</div>

<div class="mb-3">
    <x-input-label for="expiry_date" :value="__('Expiry Date (Optional)')" />
    <x-text-input id="expiry_date" name="expiry_date" type="date" :value="old('expiry_date', $product?->expiry_date?->format('Y-m-d'))" />
    <x-input-error :messages="$errors->get('expiry_date')" />
</div>

@if ($product)
    <div class="mb-3 form-check form-switch p-2 bg-light rounded border ps-5">
        <input id="is_active" type="checkbox" class="form-check-input" name="is_active" value="1" @checked(old('is_active', $product->is_active)) style="margin-left:-2.5em;">
        <label for="is_active" class="form-check-label fw-bold text-dark">{{ __('Active in Catalog & Billing POS') }}</label>
    </div>
@endif

<x-barcode-scan-modal />

<!-- Camera Barcode Scanner (additive — does not auto-submit the form) -->
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="{{ asset('js/barcode-scanner.js') }}"></script>
<script>
    document.getElementById('scan-barcode-btn').addEventListener('click', () => {
        window.openBarcodeScanner((code) => {
            document.getElementById('barcode').value = code;
            window.posToast ? window.posToast('Barcode scanned and filled in.', 'success') : null;
        });
    });
</script>
