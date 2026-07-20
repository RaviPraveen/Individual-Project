@php $product = $product ?? null; @endphp

<div class="mb-3">
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" name="name" type="text" :value="old('name', $product?->name)" required autofocus />
    <x-input-error :messages="$errors->get('name')" />
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <x-input-label for="sku" :value="__('SKU')" />
        <x-text-input id="sku" name="sku" type="text" :value="old('sku', $product?->sku)" required />
        <x-input-error :messages="$errors->get('sku')" />
    </div>
    <div class="col-md-6 mb-3">
        <x-input-label for="barcode" :value="__('Barcode')" />
        <x-text-input id="barcode" name="barcode" type="text" :value="old('barcode', $product?->barcode)" />
        <x-input-error :messages="$errors->get('barcode')" />
    </div>
</div>

<div class="mb-3">
    <x-input-label for="category_id" :value="__('Category')" />
    <select id="category_id" name="category_id" class="form-select">
        <option value="">{{ __('None') }}</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('category_id', $product?->category_id) == $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('category_id')" />
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <x-input-label for="cost_price" :value="__('Cost Price')" />
        <x-text-input id="cost_price" name="cost_price" type="number" step="0.01" min="0" :value="old('cost_price', $product?->cost_price)" required />
        <x-input-error :messages="$errors->get('cost_price')" />
    </div>
    <div class="col-md-4 mb-3">
        <x-input-label for="selling_price" :value="__('Selling Price')" />
        <x-text-input id="selling_price" name="selling_price" type="number" step="0.01" min="0" :value="old('selling_price', $product?->selling_price)" required />
        <x-input-error :messages="$errors->get('selling_price')" />
    </div>
    <div class="col-md-4 mb-3">
        <x-input-label for="unit" :value="__('Unit')" />
        <x-text-input id="unit" name="unit" type="text" :value="old('unit', $product?->unit ?? 'pcs')" required />
        <x-input-error :messages="$errors->get('unit')" />
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <x-input-label for="stock_qty" :value="__('Stock Quantity')" />
        <x-text-input id="stock_qty" name="stock_qty" type="number" min="0" :value="old('stock_qty', $product?->stock_qty ?? 0)" required />
        <x-input-error :messages="$errors->get('stock_qty')" />
    </div>
    <div class="col-md-6 mb-3">
        <x-input-label for="reorder_level" :value="__('Reorder Level')" />
        <x-text-input id="reorder_level" name="reorder_level" type="number" min="0" :value="old('reorder_level', $product?->reorder_level ?? 5)" required />
        <x-input-error :messages="$errors->get('reorder_level')" />
    </div>
</div>

@if ($product)
    <div class="mb-3 form-check">
        <input id="is_active" type="checkbox" class="form-check-input" name="is_active" value="1" @checked(old('is_active', $product->is_active))>
        <label for="is_active" class="form-check-label">{{ __('Active') }}</label>
    </div>
@endif
