<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="margin:0; padding:0; background:#F3F6F4; font-family: -apple-system, Helvetica, Arial, sans-serif; color:#1A2420;">
    <div style="max-width:480px; margin:0 auto; padding:32px 20px;">
        <div style="text-align:center; margin-bottom:20px;">
            <span style="display:inline-flex; width:44px; height:44px; background:#146C43; border-radius:10px; align-items:center; justify-content:center; font-size:22px; line-height:44px;">&#128722;</span>
            <div style="font-weight:700; font-size:18px; margin-top:8px;">{{ config('app.name') }}</div>
        </div>

        <div style="background:#FFFFFF; border:1px solid #E2E7E3; border-radius:12px; padding:24px;">
            <p style="margin:0 0 6px;">Hi {{ $sale->customer->name }},</p>
            <p style="margin:0 0 18px; color:#667169; font-size:14px;">Thanks for shopping with us! Here's your receipt summary.</p>

            <table width="100%" style="font-size:14px; border-collapse:collapse; margin-bottom:18px;">
                <tr>
                    <td style="padding:4px 0; color:#667169;">Invoice</td>
                    <td style="padding:4px 0; text-align:right; font-family: monospace;">{{ $sale->invoice_no }}</td>
                </tr>
                <tr>
                    <td style="padding:4px 0; color:#667169;">Date</td>
                    <td style="padding:4px 0; text-align:right;">{{ $sale->created_at->format('Y-m-d H:i') }}</td>
                </tr>
                <tr>
                    <td style="padding:4px 0; color:#667169;">Total Paid</td>
                    <td style="padding:4px 0; text-align:right; font-weight:700;">Rs. {{ number_format($sale->total, 2) }}</td>
                </tr>
            </table>

            <div style="background:#FBF1DE; border:1px solid #EEDDB8; border-radius:10px; padding:16px; text-align:center; margin-bottom:18px;">
                @if ($sale->points_redeemed > 0)
                    <div style="font-size:13px; color:#667169; margin-bottom:6px;">You redeemed <b>{{ $sale->points_redeemed }}</b> star points on this bill.</div>
                @endif
                <div style="font-size:13px; color:#667169;">You earned</div>
                <div style="font-size:26px; font-weight:700; color:#8F6822;">+{{ $sale->points_earned }} &#9733;</div>
                <div style="font-size:13px; color:#667169;">star points on this purchase</div>
                <div style="margin-top:10px; padding-top:10px; border-top:1px solid #EEDDB8; font-size:14px;">
                    Your balance is now <b>{{ $sale->customer->points_balance }} points</b>
                    <div style="font-size:12px; color:#667169;">(worth about Rs. {{ number_format($sale->customer->points_balance * \App\Models\BillingSetting::current()->points_redeem_value, 2) }} on your next visit)</div>
                </div>
            </div>

            <p style="font-size:13px; color:#667169; margin:0;">Show this balance to our cashier next time to redeem it against your bill.</p>
        </div>

        <p style="text-align:center; font-size:12px; color:#93A199; margin-top:20px;">
            {{ config('app.name') }} &middot; Batticaloa, Sri Lanka
        </p>
    </div>
</body>
</html>
