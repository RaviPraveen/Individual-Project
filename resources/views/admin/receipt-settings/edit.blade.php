<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0">{{ __('Receipt Designer') }}</h2>
                <div class="text-muted small">{{ __('Every change updates the preview on the right, live.') }}</div>
            </div>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('admin.receipt-settings.update') }}" id="receipt-form">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-shop me-1"></i> {{ __('Business Identity') }}</div>
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <x-input-label for="shop_name" :value="__('Shop Name')" />
                            <x-text-input id="shop_name" name="shop_name" type="text" class="rc-field" :value="old('shop_name', $settings->shop_name)" required />
                        </div>
                        <div class="col-md-6">
                            <x-input-label for="branch_name" :value="__('Branch Name')" />
                            <x-text-input id="branch_name" name="branch_name" type="text" class="rc-field" :value="old('branch_name', $settings->branch_name)" />
                        </div>
                        <div class="col-12">
                            <x-input-label for="address" :value="__('Address')" />
                            <textarea id="address" name="address" class="form-control rc-field" rows="2">{{ old('address', $settings->address) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <x-input-label for="phone" :value="__('Phone')" />
                            <x-text-input id="phone" name="phone" type="text" class="rc-field" :value="old('phone', $settings->phone)" />
                        </div>
                        <div class="col-md-6">
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" name="email" type="email" class="rc-field" :value="old('email', $settings->email)" />
                        </div>
                        <div class="col-md-6">
                            <x-input-label for="website" :value="__('Website')" />
                            <x-text-input id="website" name="website" type="text" class="rc-field" :value="old('website', $settings->website)" />
                        </div>
                        <div class="col-md-6">
                            <x-input-label for="tax_number" :value="__('Tax Number')" />
                            <x-text-input id="tax_number" name="tax_number" type="text" class="rc-field" :value="old('tax_number', $settings->tax_number)" />
                        </div>
                        <div class="col-12">
                            <x-input-label for="business_reg_number" :value="__('Business Registration Number')" />
                            <x-text-input id="business_reg_number" name="business_reg_number" type="text" class="rc-field" :value="old('business_reg_number', $settings->business_reg_number)" />
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-chat-left-text me-1"></i> {{ __('Messages') }}</div>
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <x-input-label for="thank_you_message" :value="__('Thank You Message')" />
                            <x-text-input id="thank_you_message" name="thank_you_message" type="text" class="rc-field" :value="old('thank_you_message', $settings->thank_you_message)" />
                        </div>
                        <div class="col-md-6">
                            <x-input-label for="footer_message" :value="__('Footer Message')" />
                            <x-text-input id="footer_message" name="footer_message" type="text" class="rc-field" :value="old('footer_message', $settings->footer_message)" />
                        </div>
                        <div class="col-12">
                            <x-input-label for="return_policy" :value="__('Return Policy')" />
                            <textarea id="return_policy" name="return_policy" class="form-control rc-field" rows="2">{{ old('return_policy', $settings->return_policy) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-layout-text-window me-1"></i> {{ __('Layout & Print Format') }}</div>
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <x-input-label for="paper_size" :value="__('Paper Size')" />
                            <select id="paper_size" name="paper_size" class="form-select rc-field">
                                <option value="thermal" @selected(old('paper_size', $settings->paper_size) === 'thermal')>{{ __('Thermal Roll') }}</option>
                                <option value="a4" @selected(old('paper_size', $settings->paper_size) === 'a4')>{{ __('A4') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <x-input-label for="receipt_width" :value="__('Receipt Width (thermal)')" />
                            <select id="receipt_width" name="receipt_width" class="form-select rc-field">
                                <option value="58mm" @selected(old('receipt_width', $settings->receipt_width) === '58mm')>58mm</option>
                                <option value="80mm" @selected(old('receipt_width', $settings->receipt_width) === '80mm')>80mm</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <x-input-label for="header_alignment" :value="__('Header Alignment')" />
                            <select id="header_alignment" name="header_alignment" class="form-select rc-field">
                                @foreach (['left', 'center', 'right'] as $align)
                                    <option value="{{ $align }}" @selected(old('header_alignment', $settings->header_alignment) === $align)>{{ ucfirst($align) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <x-input-label for="footer_alignment" :value="__('Footer Alignment')" />
                            <select id="footer_alignment" name="footer_alignment" class="form-select rc-field">
                                @foreach (['left', 'center', 'right'] as $align)
                                    <option value="{{ $align }}" @selected(old('footer_alignment', $settings->footer_alignment) === $align)>{{ ucfirst($align) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <x-input-label for="receipt_margin" :value="__('Receipt Margin (px)')" />
                            <x-text-input id="receipt_margin" name="receipt_margin" type="number" min="0" max="40" class="rc-field" :value="old('receipt_margin', $settings->receipt_margin)" />
                        </div>
                        <div class="col-md-6">
                            <x-input-label for="receipt_padding" :value="__('Receipt Padding (px)')" />
                            <x-text-input id="receipt_padding" name="receipt_padding" type="number" min="0" max="40" class="rc-field" :value="old('receipt_padding', $settings->receipt_padding)" />
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-fonts me-1"></i> {{ __('Typography') }}</div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <x-input-label for="font_family" :value="__('Font Family')" />
                            <select id="font_family" name="font_family" class="form-select rc-field">
                                <option value="sans-serif" @selected(old('font_family', $settings->font_family) === 'sans-serif')>{{ __('Sans Serif') }}</option>
                                <option value="serif" @selected(old('font_family', $settings->font_family) === 'serif')>{{ __('Serif') }}</option>
                                <option value="monospace" @selected(old('font_family', $settings->font_family) === 'monospace')>{{ __('Monospace') }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <x-input-label for="font_size" :value="__('Font Size (px)')" />
                            <x-text-input id="font_size" name="font_size" type="number" min="8" max="24" class="rc-field" :value="old('font_size', $settings->font_size)" />
                        </div>
                        <div class="col-md-4">
                            <x-input-label for="font_weight" :value="__('Font Weight')" />
                            <select id="font_weight" name="font_weight" class="form-select rc-field">
                                <option value="normal" @selected(old('font_weight', $settings->font_weight) === 'normal')>{{ __('Normal') }}</option>
                                <option value="medium" @selected(old('font_weight', $settings->font_weight) === 'medium')>{{ __('Medium') }}</option>
                                <option value="bold" @selected(old('font_weight', $settings->font_weight) === 'bold')>{{ __('Bold') }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-image me-1"></i> {{ __('Branding & Extras') }}</div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end mb-3">
                            <div class="col-md-6">
                                <x-input-label :value="__('Logo')" />
                                <div class="d-flex align-items-center gap-2">
                                    <div id="logo-preview-thumb" class="border rounded d-flex align-items-center justify-content-center" style="width:56px;height:56px;overflow:hidden;background:var(--pos-surface-2);">
                                        @if ($settings->logo_path)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo_path) }}" alt="Logo" style="max-width:100%;max-height:100%;">
                                        @else
                                            <i class="bi bi-image text-muted"></i>
                                        @endif
                                    </div>
                                    <input type="file" id="logo-input" accept="image/*" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="col-md-6 d-flex gap-2">
                                <button type="button" id="upload-logo-btn" class="btn btn-outline-primary btn-sm">{{ __('Upload Logo') }}</button>
                                <button type="button" id="remove-logo-btn" class="btn btn-outline-danger btn-sm" @if (! $settings->logo_path) disabled @endif>{{ __('Remove Logo') }}</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mb-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                    <button type="button" class="btn btn-outline-secondary" id="print-test-btn"><i class="bi bi-printer me-1"></i>{{ __('Print Test') }}</button>
                    <a href="{{ route('admin.receipt-settings.pdf') }}" class="btn btn-outline-secondary" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>{{ __('Download PDF') }}</a>
                    <form method="POST" action="{{ route('admin.receipt-settings.reset') }}" onsubmit="return confirm('{{ __('Reset all receipt settings to defaults?') }}');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('Reset') }}</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="sticky-top" style="top: 90px;">
                    <div class="text-center text-muted small mb-2 text-uppercase" style="letter-spacing:.05em;">{{ __('Live Preview') }}</div>
                    <div class="d-flex justify-content-center">
                        <div id="receipt-preview-paper" class="bg-white shadow-sm border" style="transition: width .15s ease;">
                            <div id="receipt-preview-content"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script>
        const preview = document.getElementById('receipt-preview-content');
        const paper = document.getElementById('receipt-preview-paper');
        const form = document.getElementById('receipt-form');

        const sample = {
            invoice_no: 'INV-20260101-0001',
            date: '{{ now()->format('Y-m-d H:i') }}',
            cashier: 'Cashier User',
            customer: 'Walk-in',
            items: [
                { name: 'Rice 5kg', qty: 2, price: 1200.00, total: 2400.00 },
                { name: 'Milk Powder 400g', qty: 1, price: 650.00, total: 650.00 },
                { name: 'Sugar 1kg', qty: 3, price: 280.00, total: 840.00 },
            ],
            subtotal: 3890.00,
            discount: 100.00,
            tax: 0.00,
            total: 3790.00,
            points_earned: 37,
        };

        function fieldValue(name) {
            const el = form.querySelector(`[name="${name}"]`);
            if (!el) return '';
            if (el.type === 'checkbox') return el.checked;
            return el.value;
        }

        function esc(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        function renderPreview() {
            const align = (v) => v === 'left' ? 'left' : v === 'right' ? 'right' : 'center';
            const fontWeightMap = { normal: 400, medium: 500, bold: 700 };

            const shopName = fieldValue('shop_name') || 'Shop Name';
            const branchName = fieldValue('branch_name');
            const address = fieldValue('address');
            const phone = fieldValue('phone');
            const email = fieldValue('email');
            const website = fieldValue('website');
            const taxNumber = fieldValue('tax_number');
            const bizReg = fieldValue('business_reg_number');
            const thankYou = fieldValue('thank_you_message') || 'Thank you for shopping with us!';
            const footerMsg = fieldValue('footer_message');
            const returnPolicy = fieldValue('return_policy');
            const paperSize = fieldValue('paper_size');
            const width = fieldValue('receipt_width');
            const headerAlign = align(fieldValue('header_alignment'));
            const footerAlign = align(fieldValue('footer_alignment'));
            const margin = parseInt(fieldValue('receipt_margin')) || 0;
            const padding = parseInt(fieldValue('receipt_padding')) || 0;
            const fontFamily = fieldValue('font_family') || 'sans-serif';
            const fontSize = parseInt(fieldValue('font_size')) || 12;
            const fontWeight = fontWeightMap[fieldValue('font_weight')] || 400;
            const logoImg = document.querySelector('#logo-preview-thumb img');

            const widthPx = paperSize === 'a4' ? 420 : (width === '58mm' ? 219 : 302);
            paper.style.width = widthPx + 'px';
            paper.style.margin = margin + 'px';
            paper.style.padding = padding + 'px';
            paper.style.fontFamily = fontFamily;
            paper.style.fontSize = fontSize + 'px';
            paper.style.fontWeight = fontWeight;

            let itemRows = sample.items.map(i => `
                <tr>
                    <td style="padding:2px 0;">${esc(i.name)}</td>
                    <td style="padding:2px 0;text-align:right;">${i.qty}</td>
                    <td style="padding:2px 0;text-align:right;">${i.price.toFixed(2)}</td>
                    <td style="padding:2px 0;text-align:right;">${i.total.toFixed(2)}</td>
                </tr>
            `).join('');

            preview.innerHTML = `
                <div style="text-align:${headerAlign};margin-bottom:8px;">
                    ${logoImg ? `<img src="${logoImg.src}" style="max-height:40px;max-width:100%;margin-bottom:4px;">` : ''}
                    <div style="font-weight:700;font-size:${fontSize + 3}px;">${esc(shopName)}</div>
                    ${branchName ? `<div>${esc(branchName)}</div>` : ''}
                    ${address ? `<div style="font-size:${fontSize - 1}px;">${esc(address)}</div>` : ''}
                    ${phone ? `<div style="font-size:${fontSize - 1}px;">Tel: ${esc(phone)}</div>` : ''}
                    ${email ? `<div style="font-size:${fontSize - 1}px;">${esc(email)}</div>` : ''}
                    ${website ? `<div style="font-size:${fontSize - 1}px;">${esc(website)}</div>` : ''}
                    ${taxNumber ? `<div style="font-size:${fontSize - 1}px;">Tax No: ${esc(taxNumber)}</div>` : ''}
                    ${bizReg ? `<div style="font-size:${fontSize - 1}px;">Reg No: ${esc(bizReg)}</div>` : ''}
                </div>
                <hr style="border-top:1px dashed #999;">
                <div style="font-size:${fontSize - 1}px;">
                    <div style="display:flex;justify-content:space-between;"><span>Invoice</span><span>${sample.invoice_no}</span></div>
                    <div style="display:flex;justify-content:space-between;"><span>Date</span><span>${sample.date}</span></div>
                    <div style="display:flex;justify-content:space-between;"><span>Cashier</span><span>${sample.cashier}</span></div>
                    <div style="display:flex;justify-content:space-between;"><span>Customer</span><span>${sample.customer}</span></div>
                </div>
                <hr style="border-top:1px dashed #999;">
                <table style="width:100%;border-collapse:collapse;font-size:${fontSize - 1}px;">
                    <thead>
                        <tr style="border-bottom:1px solid #999;">
                            <th style="text-align:left;padding-bottom:3px;">Item</th>
                            <th style="text-align:right;padding-bottom:3px;">Qty</th>
                            <th style="text-align:right;padding-bottom:3px;">Price</th>
                            <th style="text-align:right;padding-bottom:3px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>${itemRows}</tbody>
                </table>
                <hr style="border-top:1px dashed #999;">
                <div style="font-size:${fontSize - 1}px;">
                    <div style="display:flex;justify-content:space-between;"><span>Subtotal</span><span>${sample.subtotal.toFixed(2)}</span></div>
                    <div style="display:flex;justify-content:space-between;"><span>Discount</span><span>${sample.discount.toFixed(2)}</span></div>
                    <div style="display:flex;justify-content:space-between;"><span>Tax</span><span>${sample.tax.toFixed(2)}</span></div>
                    <div style="display:flex;justify-content:space-between;font-weight:700;font-size:${fontSize + 1}px;"><span>Total</span><span>${sample.total.toFixed(2)}</span></div>
                </div>
                <div style="text-align:center;margin-top:8px;font-size:${fontSize - 1}px;background:#FBF1DE;border-radius:6px;padding:6px;">
                    &#9733; +${sample.points_earned} star points earned
                </div>
                <hr style="border-top:1px dashed #999;">
                <div style="text-align:${footerAlign};font-size:${fontSize - 1}px;">
                    <div>${esc(thankYou)}</div>
                    ${footerMsg ? `<div style="margin-top:4px;">${esc(footerMsg)}</div>` : ''}
                    ${returnPolicy ? `<div style="margin-top:4px;font-size:${fontSize - 2}px;color:#666;">${esc(returnPolicy)}</div>` : ''}
                </div>
            `;
        }

        form.addEventListener('input', renderPreview);
        form.addEventListener('change', renderPreview);
        renderPreview();

        document.getElementById('upload-logo-btn').addEventListener('click', () => {
            const fileInput = document.getElementById('logo-input');
            if (!fileInput.files.length) {
                alert('{{ __('Choose an image file first.') }}');
                return;
            }
            const formData = new FormData();
            formData.append('logo', fileInput.files[0]);
            const csrf = document.querySelector('meta[name="csrf-token"]').content;

            fetch('{{ route('admin.receipt-settings.logo.upload') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: formData,
            })
                .then(r => r.json())
                .then(data => {
                    if (data.logo_url) {
                        document.getElementById('logo-preview-thumb').innerHTML = `<img src="${data.logo_url}" style="max-width:100%;max-height:100%;">`;
                        document.getElementById('remove-logo-btn').disabled = false;
                        renderPreview();
                        if (window.posToast) posToast('{{ __('Logo uploaded.') }}', 'success');
                    }
                });
        });

        document.getElementById('remove-logo-btn').addEventListener('click', () => {
            if (!confirm('{{ __('Remove the current logo?') }}')) return;
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            fetch('{{ route('admin.receipt-settings.logo.remove') }}', {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf },
            }).then(() => window.location.reload());
        });

        document.getElementById('print-test-btn').addEventListener('click', () => {
            const printWindow = window.open('', '_blank', 'width=420,height=700');
            printWindow.document.write(`
                <html><head><title>Print Test</title></head>
                <body style="margin:16px;">${paper.outerHTML}</body></html>
            `);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => printWindow.print(), 300);
        });
    </script>
</x-admin-layout>
