{{--
    Shared receipt markup used by both the on-screen receipt and the PDF
    export. Deliberately avoids flexbox/grid — dompdf's CSS engine doesn't
    support them, so this stays table/inline-block based to render
    identically in a browser and in the generated PDF.

    Expects: $settings (ReceiptSetting) and $data (array):
    invoice_no, date, cashier_name, customer_name, payment_method,
    items: [['name','qty','price','total'], ...],
    subtotal, discount, tax, total, points_earned, points_redeemed, points_balance
--}}
@php
    $align = fn ($v) => in_array($v, ['left', 'right']) ? $v : 'center';
    $fontWeightMap = ['normal' => 400, 'medium' => 500, 'bold' => 700];
    $widthPx = $settings->paper_size === 'a4' ? 420 : ($settings->receipt_width === '58mm' ? 219 : 302);

    // This partial renders both the on-screen HTML receipt and the PDF
    // export. dompdf has `enable_remote` off (the default — fetching
    // remote URLs at PDF-render time is a security/reliability risk), so
    // it can only embed the logo from a local filesystem path; a browser
    // can only load it from an actual URL. Using the filesystem path
    // unconditionally left the on-screen receipt's logo broken.
    $forPdf = $forPdf ?? false;
    $logoUrl = $settings->logo_path
        ? ($forPdf ? \Illuminate\Support\Facades\Storage::disk('public')->path($settings->logo_path) : \Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo_path))
        : null;
@endphp
<div style="
    width: {{ $widthPx }}px;
    margin: {{ $settings->receipt_margin }}px auto;
    padding: {{ $settings->receipt_padding }}px;
    font-family: {{ $settings->font_family }};
    font-size: {{ $settings->font_size }}px;
    font-weight: {{ $fontWeightMap[$settings->font_weight] ?? 400 }};
    color: #1A2420;
">
    <div style="text-align: {{ $align($settings->header_alignment) }}; margin-bottom: 8px;">
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" style="max-height: 40px; max-width: 100%; margin-bottom: 4px;">
        @endif
        <div style="font-weight: 700; font-size: {{ $settings->font_size + 3 }}px;">{{ $settings->shop_name }}</div>
        @if ($settings->branch_name)<div>{{ $settings->branch_name }}</div>@endif
        @if ($settings->address)<div style="font-size: {{ $settings->font_size - 1 }}px;">{{ $settings->address }}</div>@endif
        @if ($settings->phone)<div style="font-size: {{ $settings->font_size - 1 }}px;">Tel: {{ $settings->phone }}</div>@endif
        @if ($settings->email)<div style="font-size: {{ $settings->font_size - 1 }}px;">{{ $settings->email }}</div>@endif
        @if ($settings->website)<div style="font-size: {{ $settings->font_size - 1 }}px;">{{ $settings->website }}</div>@endif
        @if ($settings->tax_number)<div style="font-size: {{ $settings->font_size - 1 }}px;">Tax No: {{ $settings->tax_number }}</div>@endif
        @if ($settings->business_reg_number)<div style="font-size: {{ $settings->font_size - 1 }}px;">Reg No: {{ $settings->business_reg_number }}</div>@endif
    </div>

    <hr style="border-top: 1px dashed #999;">

    <table style="width: 100%; font-size: {{ $settings->font_size - 1 }}px; border-collapse: collapse;">
        <tr><td>Invoice</td><td style="text-align: right;">{{ $data['invoice_no'] }}</td></tr>
        <tr><td>Date</td><td style="text-align: right;">{{ $data['date'] }}</td></tr>
        <tr><td>Cashier</td><td style="text-align: right;">{{ $data['cashier_name'] }}</td></tr>
        <tr><td>Customer</td><td style="text-align: right;">{{ $data['customer_name'] }}</td></tr>
        @if (! empty($data['payment_method']))
            <tr><td>Payment</td><td style="text-align: right;">{{ ucfirst($data['payment_method']) }}</td></tr>
        @endif
    </table>

    <hr style="border-top: 1px dashed #999;">

    <table style="width: 100%; border-collapse: collapse; font-size: {{ $settings->font_size - 1 }}px;">
        <thead>
            <tr style="border-bottom: 1px solid #999;">
                <th style="text-align: left; padding-bottom: 3px;">Item</th>
                <th style="text-align: right; padding-bottom: 3px;">Qty</th>
                <th style="text-align: right; padding-bottom: 3px;">Price</th>
                <th style="text-align: right; padding-bottom: 3px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['items'] as $item)
                <tr>
                    <td style="padding: 2px 0;">{{ $item['name'] }}</td>
                    <td style="padding: 2px 0; text-align: right;">{{ $item['qty'] }}</td>
                    <td style="padding: 2px 0; text-align: right;">{{ number_format($item['price'], 2) }}</td>
                    <td style="padding: 2px 0; text-align: right;">{{ number_format($item['total'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <hr style="border-top: 1px dashed #999;">

    <table style="width: 100%; font-size: {{ $settings->font_size - 1 }}px; border-collapse: collapse;">
        <tr><td>Subtotal</td><td style="text-align: right;">{{ number_format($data['subtotal'], 2) }}</td></tr>
        <tr><td>Discount</td><td style="text-align: right;">{{ number_format($data['discount'], 2) }}</td></tr>
        @if (! empty($data['points_redeemed']))
            <tr><td>Points Redeemed ({{ $data['points_redeemed'] }})</td><td style="text-align: right;">-{{ number_format($data['redemption_value'] ?? 0, 2) }}</td></tr>
        @endif
        <tr><td>Tax</td><td style="text-align: right;">{{ number_format($data['tax'], 2) }}</td></tr>
        @if (! empty($data['bag_fee']))
            <tr><td>Bag Fee</td><td style="text-align: right;">{{ number_format($data['bag_fee'], 2) }}</td></tr>
        @endif
        <tr style="font-weight: 700; font-size: {{ $settings->font_size + 1 }}px;"><td>Total</td><td style="text-align: right;">{{ number_format($data['total'], 2) }}</td></tr>
    </table>

    @if (! empty($data['points_earned']) || ! empty($data['points_redeemed']))
        <div style="text-align: center; margin-top: 8px; font-size: {{ $settings->font_size - 1 }}px; background: #FBF1DE; border-radius: 6px; padding: 6px;">
            @if (! empty($data['points_earned']))
                &#9733; +{{ $data['points_earned'] }} star points earned
            @endif
            @if (! empty($data['points_balance']))
                <br>Balance: {{ $data['points_balance'] }} points
            @endif
        </div>
    @endif

    <hr style="border-top: 1px dashed #999;">

    <div style="text-align: {{ $align($settings->footer_alignment) }}; font-size: {{ $settings->font_size - 1 }}px;">
        <div>{{ $settings->thank_you_message }}</div>
        @if ($settings->footer_message)<div style="margin-top: 4px;">{{ $settings->footer_message }}</div>@endif
        @if ($settings->return_policy)<div style="margin-top: 4px; font-size: {{ $settings->font_size - 2 }}px; color: #666;">{{ $settings->return_policy }}</div>@endif
    </div>
</div>
