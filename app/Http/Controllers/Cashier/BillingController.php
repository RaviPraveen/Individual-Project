<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Mail\LoyaltyPointsEarnedMail;
use App\Models\AiLog;
use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\LoyaltyPointTransaction;
use App\Models\Product;
use App\Models\ReceiptSetting;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Services\GeminiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BillingController extends Controller
{
    public function index(): View
    {
        $billingSettings = BillingSetting::current();

        return view('cashier.billing.index', [
            'maxDiscountPercent' => config('billing.max_discount_percent'),
            'taxPercent' => config('billing.tax_percent'),
            'pointsRedeemValue' => (float) $billingSettings->points_redeem_value,
            'pointsEarnPercent' => $billingSettings->earnPercent(),
            'bagFee' => (float) $billingSettings->bag_fee,
        ]);
    }

    public function quickCreateCustomer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50', 'unique:customers,phone'],
        ]);

        $customer = Customer::create($validated);
        $customer->refresh();

        return response()->json([
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'points_balance' => $customer->points_balance,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $maxDiscount = config('billing.max_discount_percent');

        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:'.$maxDiscount],
            'points_to_redeem' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['required', 'in:cash,card,other'],
            'wants_bag' => ['sometimes', 'boolean'],
        ]);

        $billingSettings = BillingSetting::current();

        try {
            $sale = DB::transaction(function () use ($validated, $request, $billingSettings) {
                $productIds = collect($validated['items'])->pluck('product_id');
                $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

                $quantitiesByProduct = collect($validated['items'])
                    ->groupBy('product_id')
                    ->map(fn ($rows) => $rows->sum('quantity'));

                foreach ($quantitiesByProduct as $productId => $requestedQty) {
                    $product = $products->get($productId);

                    if (! $product || $requestedQty > $product->stock_qty) {
                        throw new RuntimeException(
                            'Insufficient stock for '.($product->name ?? "product #{$productId}").'. Available: '.($product->stock_qty ?? 0).'.'
                        );
                    }
                }

                $customer = null;
                if (! empty($validated['customer_id'])) {
                    $customer = Customer::where('id', $validated['customer_id'])->lockForUpdate()->first();
                }

                $subtotal = collect($validated['items'])->sum(
                    fn ($item) => $products->get($item['product_id'])->selling_price * $item['quantity']
                );

                $discountPercent = $validated['discount_percent'] ?? 0;
                $discountAmount = round($subtotal * $discountPercent / 100, 2);
                $taxPercent = config('billing.tax_percent');
                $taxAmount = round(($subtotal - $discountAmount) * $taxPercent / 100, 2);
                $totalBeforePoints = $subtotal - $discountAmount + $taxAmount;

                // Redeem first, capped by the customer's balance and by the bill itself
                // (a bill can never go negative), then earn on the amount actually paid.
                $redeemValuePerPoint = (float) $billingSettings->points_redeem_value;
                $pointsRequested = (int) ($validated['points_to_redeem'] ?? 0);
                $pointsRequested = $customer ? min($pointsRequested, $customer->points_balance) : 0;
                $maxRedeemableByBill = $redeemValuePerPoint > 0 ? (int) floor($totalBeforePoints / $redeemValuePerPoint) : 0;
                $pointsToRedeem = max(0, min($pointsRequested, $maxRedeemableByBill));
                $redemptionValue = round($pointsToRedeem * $redeemValuePerPoint, 2);

                $total = $totalBeforePoints - $redemptionValue;

                $pointsEarnPercent = $billingSettings->earnPercent();
                $pointsEarned = $customer ? (int) floor($total * $pointsEarnPercent / 100) : 0;

                // Bag fee is a flat per-transaction charge — not part of the
                // merchandise subtotal, not discounted, not taxed, and not
                // counted toward star points (which are earned on the amount
                // paid for goods, not on service fees). Added after points
                // are calculated so it never affects the earn rate.
                $bagFee = $request->boolean('wants_bag') ? (float) $billingSettings->bag_fee : 0;
                $total += $bagFee;

                $sale = Sale::create([
                    'invoice_no' => $this->generateInvoiceNumber(),
                    'cashier_id' => $request->user()->id,
                    'customer_id' => $customer?->id,
                    'subtotal' => $subtotal,
                    'discount' => $discountAmount,
                    'tax' => $taxAmount,
                    'bag_fee' => $bagFee,
                    'total' => $total,
                    'payment_method' => $validated['payment_method'],
                    'points_earned' => $pointsEarned,
                    'points_redeemed' => $pointsToRedeem,
                    'redemption_value' => $redemptionValue,
                ]);

                foreach ($quantitiesByProduct as $productId => $quantity) {
                    $product = $products->get($productId);

                    $sale->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $product->selling_price,
                        'line_total' => $product->selling_price * $quantity,
                    ]);

                    $product->decrement('stock_qty', $quantity);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'out',
                        'quantity' => $quantity,
                        'reason' => 'sale',
                        'recorded_by' => $request->user()->id,
                    ]);
                }

                if ($customer) {
                    $runningBalance = $customer->points_balance;

                    if ($pointsToRedeem > 0) {
                        $runningBalance -= $pointsToRedeem;

                        LoyaltyPointTransaction::create([
                            'customer_id' => $customer->id,
                            'sale_id' => $sale->id,
                            'type' => 'redeem',
                            'points' => -$pointsToRedeem,
                            'balance_after' => $runningBalance,
                            'note' => 'Redeemed at checkout',
                        ]);
                    }

                    if ($pointsEarned > 0) {
                        $runningBalance += $pointsEarned;

                        LoyaltyPointTransaction::create([
                            'customer_id' => $customer->id,
                            'sale_id' => $sale->id,
                            'type' => 'earn',
                            'points' => $pointsEarned,
                            'balance_after' => $runningBalance,
                            'note' => 'Earned at checkout',
                        ]);
                    }

                    $customer->points_balance = $runningBalance;
                    $customer->save();
                }

                return $sale;
            });
        } catch (RuntimeException $e) {
            return redirect()->route('cashier.billing.index')->with('error', $e->getMessage());
        }

        $sale->load('items.product', 'customer');

        if ($sale->customer?->email) {
            try {
                Mail::to($sale->customer->email)->send(new LoyaltyPointsEarnedMail($sale));
            } catch (Throwable $e) {
                report($e);
            }
        }

        Cache::put("customer-display:{$request->user()->id}", [
            'status' => 'completed',
            'invoice_no' => $sale->invoice_no,
            'total' => (float) $sale->total,
            'points_earned' => $sale->points_earned,
            'points_redeemed' => $sale->points_redeemed,
            'points_balance' => $sale->customer?->points_balance,
            'customer_name' => $sale->customer?->name,
        ], 15);

        return redirect()->route('cashier.billing.receipt', $sale)->with('success', 'Sale completed.');
    }

    public function upsellSuggestion(Request $request, GeminiService $gemini): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'cart_items' => ['present', 'array'],
            'cart_items.*' => ['string', 'max:255'],
        ]);

        if (! $gemini->isConfigured()) {
            return response()->json(['suggestion' => null]);
        }

        $customer = Customer::find($validated['customer_id']);

        $history = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.customer_id', $customer->id)
            ->selectRaw('products.name, SUM(sale_items.quantity) as qty')
            ->groupBy('products.name')
            ->orderByDesc('qty')
            ->limit(5)
            ->get()
            ->map(fn ($row) => "{$row->name} (bought {$row->qty} total)")
            ->implode(', ');

        if ($history === '') {
            return response()->json(['suggestion' => null]);
        }

        $cartList = implode(', ', $validated['cart_items']) ?: 'nothing yet';

        $prompt = "You are assisting a supermarket cashier at Welcome Foodcity, Batticaloa. ".
            "A returning customer is at the till. Based ONLY on the data below, give the cashier ONE short, ".
            "natural suggestion (max 20 words) for something to mention to this customer — e.g. a product they ".
            "usually buy that isn't in today's cart. If nothing is worth suggesting, reply exactly: NONE\n\n".
            "Customer's usual purchases: {$history}\n".
            "In today's cart already: {$cartList}";

        $suggestion = $gemini->generate($prompt);

        if ($suggestion === null || strtoupper(trim($suggestion)) === 'NONE') {
            return response()->json(['suggestion' => null]);
        }

        AiLog::create([
            'user_id' => $request->user()->id,
            'query' => "Upsell suggestion for customer #{$customer->id} ({$customer->name}) at checkout",
            'response' => $suggestion,
        ]);

        return response()->json(['suggestion' => $suggestion]);
    }

    public function receipt(Sale $sale): View
    {
        $sale->load('items.product', 'customer', 'cashier');

        return view('cashier.billing.receipt', [
            'sale' => $sale,
            'settings' => ReceiptSetting::current(),
            'data' => $this->receiptData($sale),
        ]);
    }

    public function receiptPdf(Sale $sale): Response
    {
        $sale->load('items.product', 'customer', 'cashier');

        $pdf = Pdf::loadView('receipts.pdf', [
            'settings' => ReceiptSetting::current(),
            'data' => $this->receiptData($sale),
        ]);

        return $pdf->stream("{$sale->invoice_no}.pdf");
    }

    private function receiptData(Sale $sale): array
    {
        return [
            'invoice_no' => $sale->invoice_no,
            'date' => $sale->created_at->format('Y-m-d H:i'),
            'cashier_name' => $sale->cashier->name,
            'customer_name' => $sale->customer->name ?? 'Walk-in',
            'payment_method' => $sale->payment_method,
            'items' => $sale->items->map(fn ($item) => [
                'name' => $item->product->name,
                'qty' => $item->quantity,
                'price' => (float) $item->unit_price,
                'total' => (float) $item->line_total,
            ])->all(),
            'subtotal' => (float) $sale->subtotal,
            'discount' => (float) $sale->discount,
            'tax' => (float) $sale->tax,
            'bag_fee' => (float) $sale->bag_fee,
            'total' => (float) $sale->total,
            'points_earned' => $sale->points_earned,
            'points_redeemed' => $sale->points_redeemed,
            'redemption_value' => (float) $sale->redemption_value,
            'points_balance' => $sale->customer?->points_balance,
        ];
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd').'-';

        do {
            $sequence = Sale::where('invoice_no', 'like', $prefix.'%')->count() + 1;
            $candidate = $prefix.str_pad($sequence, 4, '0', STR_PAD_LEFT);
        } while (Sale::where('invoice_no', $candidate)->exists());

        return $candidate;
    }
}
