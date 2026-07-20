{{--
    Proof-of-return document. Mirrors receipts/_body.blade.php's
    table/inline-block layout (no flexbox) and branding so it stays visually
    consistent with the sale receipt, but this one is a plain in-app view
    (no PDF/print variant needed for this pass).
--}}
<div style="max-width: 420px; margin: 0 auto; font-size: 14px; color: #1A2420;">
    <div style="text-align: center; margin-bottom: 8px;">
        <div style="font-weight: 700; font-size: 17px;">{{ $settings->shop_name }}</div>
        @if ($settings->address)<div style="font-size: 13px;">{{ $settings->address }}</div>@endif
        @if ($settings->phone)<div style="font-size: 13px;">Tel: {{ $settings->phone }}</div>@endif
    </div>

    <hr style="border-top: 1px dashed #999;">

    <div style="text-align: center; font-weight: 700; margin-bottom: 8px;">{{ __('RETURN / REFUND RECEIPT') }}</div>

    <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
        <tr><td>{{ __('Return No') }}</td><td style="text-align: right;">{{ $saleReturn->return_no }}</td></tr>
        <tr><td>{{ __('Original Invoice') }}</td><td style="text-align: right;">{{ $saleReturn->sale->invoice_no }}</td></tr>
        <tr><td>{{ __('Date') }}</td><td style="text-align: right;">{{ $saleReturn->created_at->format('Y-m-d H:i') }}</td></tr>
        <tr><td>{{ __('Processed By') }}</td><td style="text-align: right;">{{ $saleReturn->processedBy->name }}</td></tr>
        <tr><td>{{ __('Customer') }}</td><td style="text-align: right;">{{ $saleReturn->sale->customer->name ?? 'Walk-in' }}</td></tr>
        <tr><td>{{ __('Refund Method') }}</td><td style="text-align: right;">{{ ucfirst($saleReturn->refund_method) }}</td></tr>
    </table>

    <hr style="border-top: 1px dashed #999;">

    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
        <thead>
            <tr style="border-bottom: 1px solid #999;">
                <th style="text-align: left; padding-bottom: 3px;">{{ __('Item') }}</th>
                <th style="text-align: right; padding-bottom: 3px;">{{ __('Qty') }}</th>
                <th style="text-align: right; padding-bottom: 3px;">{{ __('Price') }}</th>
                <th style="text-align: right; padding-bottom: 3px;">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($saleReturn->items as $item)
                <tr>
                    <td style="padding: 2px 0;">{{ $item->product->name }}</td>
                    <td style="padding: 2px 0; text-align: right;">{{ $item->quantity }}</td>
                    <td style="padding: 2px 0; text-align: right;">{{ number_format($item->unit_price, 2) }}</td>
                    <td style="padding: 2px 0; text-align: right;">{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <hr style="border-top: 1px dashed #999;">

    <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
        <tr><td>{{ __('Subtotal Refunded') }}</td><td style="text-align: right;">{{ number_format($saleReturn->subtotal_refunded, 2) }}</td></tr>
        <tr><td>{{ __('Discount Adjustment') }}</td><td style="text-align: right;">-{{ number_format($saleReturn->discount_refunded, 2) }}</td></tr>
        <tr><td>{{ __('Tax Refunded') }}</td><td style="text-align: right;">{{ number_format($saleReturn->tax_refunded, 2) }}</td></tr>
        <tr style="font-weight: 700; font-size: 15px;"><td>{{ __('Total Refunded') }}</td><td style="text-align: right;">{{ number_format($saleReturn->total_refunded, 2) }}</td></tr>
    </table>

    @if ($saleReturn->points_clawed_back > 0)
        <div style="text-align: center; margin-top: 8px; font-size: 13px; background: #FBF1DE; border-radius: 6px; padding: 6px;">
            &#9733; {{ $saleReturn->points_clawed_back }} {{ __('star points reversed for this return') }}
        </div>
    @endif

    @if ($saleReturn->reason)
        <hr style="border-top: 1px dashed #999;">
        <div style="font-size: 13px;"><strong>{{ __('Reason:') }}</strong> {{ $saleReturn->reason }}</div>
    @endif
</div>
