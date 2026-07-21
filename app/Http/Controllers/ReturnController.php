<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LoyaltyPointTransaction;
use App\Models\Product;
use App\Models\ReceiptSetting;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class ReturnController extends Controller
{
    public function index(Request $request): View
    {
        $isAdmin = $request->user()->role === 'admin';

        $scoped = SaleReturn::query()->when(! $isAdmin, fn ($q) => $q->where('processed_by', $request->user()->id));

        $returns = (clone $scoped)->with('sale', 'processedBy')->latest()->paginate(15);

        $stats = [
            'total_count' => (clone $scoped)->count(),
            'month_refunded' => (clone $scoped)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total_refunded'),
            'month_count' => (clone $scoped)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
        ];

        return view($isAdmin ? 'admin.returns.index' : 'cashier.returns.index', compact('returns', 'stats'));
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_no' => ['required', 'string'],
        ]);

        $sale = Sale::with('items.product', 'customer')
            ->where('invoice_no', trim($validated['invoice_no']))
            ->first();

        if (! $sale) {
            return response()->json(['message' => 'No sale found with that invoice number.'], 404);
        }

        $alreadyReturned = DB::table('sale_return_items')
            ->whereIn('sale_item_id', $sale->items->pluck('id'))
            ->selectRaw('sale_item_id, SUM(quantity) as qty')
            ->groupBy('sale_item_id')
            ->pluck('qty', 'sale_item_id');

        $items = $sale->items->map(function ($item) use ($alreadyReturned) {
            $returned = (int) ($alreadyReturned[$item->id] ?? 0);

            return [
                'sale_item_id' => $item->id,
                'product_name' => $item->product->name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'already_returned' => $returned,
                'max_returnable' => $item->quantity - $returned,
            ];
        })->values()->all();

        return response()->json([
            'sale_id' => $sale->id,
            'invoice_no' => $sale->invoice_no,
            'date' => $sale->created_at->format('Y-m-d H:i'),
            'customer_name' => $sale->customer->name ?? 'Walk-in',
            'payment_method' => $sale->payment_method,
            'subtotal' => (float) $sale->subtotal,
            'discount' => (float) $sale->discount,
            'tax' => (float) $sale->tax,
            'total' => (float) $sale->total,
            'items' => $items,
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'sale_id' => ['required', 'exists:sales,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', 'exists:sale_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'refund_method' => ['required', 'in:cash,card,other'],
        ]);

        $requestedItems = collect($validated['items'])->filter(fn ($i) => $i['quantity'] > 0)->values();

        if ($requestedItems->isEmpty()) {
            return redirect()->back()->withInput()->with('error', 'Select at least one item and quantity to return.');
        }

        try {
            $saleReturn = DB::transaction(function () use ($validated, $requestedItems, $request) {
                $sale = Sale::with('items')->lockForUpdate()->findOrFail($validated['sale_id']);

                $taxPercent = (float) config('billing.tax_percent');
                $discountRatio = $sale->subtotal > 0 ? ((float) $sale->discount / (float) $sale->subtotal) : 0;
                // Uses the value actually redeemed at time of sale (not the
                // current BillingSetting rate) so a refund stays correct
                // even if the admin has since changed the redeem rate.
                $redemptionValue = (float) $sale->redemption_value;

                $subtotalRefunded = 0;
                $discountRefunded = 0;
                $taxRefunded = 0;
                $totalRefunded = 0;
                $lineData = [];

                foreach ($requestedItems as $reqItem) {
                    $saleItem = $sale->items->firstWhere('id', $reqItem['sale_item_id']);

                    if (! $saleItem) {
                        throw new RuntimeException('That item does not belong to this sale.');
                    }

                    $alreadyReturned = (int) $saleItem->returnItems()->sum('quantity');
                    $maxReturnable = $saleItem->quantity - $alreadyReturned;

                    if ($reqItem['quantity'] > $maxReturnable) {
                        throw new RuntimeException("Cannot return {$reqItem['quantity']}; only {$maxReturnable} left available to return for that item.");
                    }

                    $lineRaw = round($saleItem->unit_price * $reqItem['quantity'], 2);
                    $lineDiscount = round($lineRaw * $discountRatio, 2);
                    $lineAfterDiscount = $lineRaw - $lineDiscount;
                    $lineTax = round($lineAfterDiscount * $taxPercent / 100, 2);
                    $lineRedemptionShare = $sale->subtotal > 0
                        ? round($redemptionValue * ($lineRaw / (float) $sale->subtotal), 2)
                        : 0;
                    $lineTotal = $lineAfterDiscount + $lineTax - $lineRedemptionShare;

                    $subtotalRefunded += $lineRaw;
                    $discountRefunded += $lineDiscount;
                    $taxRefunded += $lineTax;
                    $totalRefunded += $lineTotal;

                    $lineData[] = [
                        'sale_item_id' => $saleItem->id,
                        'product_id' => $saleItem->product_id,
                        'quantity' => $reqItem['quantity'],
                        'unit_price' => $saleItem->unit_price,
                        'line_total' => $lineRaw,
                    ];
                }

                // Claw back a proportional share of points earned on the returned
                // portion — the customer can't keep loyalty points for merchandise
                // they no longer own. Redeemed points are deliberately NOT restored:
                // once spent, they are treated the same as a non-refundable reward,
                // consistent with most real loyalty programs.
                $pointsClawedBack = 0;
                $customerBalanceAfter = null;

                if ($sale->customer_id && $sale->points_earned > 0 && $sale->subtotal > 0) {
                    $earnedRatio = $subtotalRefunded / (float) $sale->subtotal;
                    $pointsClawedBack = (int) floor($sale->points_earned * $earnedRatio);

                    $customer = Customer::where('id', $sale->customer_id)->lockForUpdate()->first();
                    $pointsClawedBack = min($pointsClawedBack, $customer->points_balance);

                    if ($pointsClawedBack > 0) {
                        $customer->points_balance -= $pointsClawedBack;
                        $customer->save();
                        $customerBalanceAfter = $customer->points_balance;
                    }
                }

                $returnNo = $this->generateReturnNumber();

                $saleReturn = SaleReturn::create([
                    'return_no' => $returnNo,
                    'sale_id' => $sale->id,
                    'processed_by' => $request->user()->id,
                    'reason' => $validated['reason'] ?? null,
                    'refund_method' => $validated['refund_method'],
                    'subtotal_refunded' => $subtotalRefunded,
                    'discount_refunded' => $discountRefunded,
                    'tax_refunded' => $taxRefunded,
                    'total_refunded' => max(0, $totalRefunded),
                    'points_clawed_back' => $pointsClawedBack,
                ]);

                foreach ($lineData as $line) {
                    $saleReturn->items()->create($line);

                    $product = Product::where('id', $line['product_id'])->lockForUpdate()->first();
                    $product->increment('stock_qty', $line['quantity']);

                    StockMovement::create([
                        'product_id' => $line['product_id'],
                        'type' => 'in',
                        'quantity' => $line['quantity'],
                        'reason' => 'return',
                        'recorded_by' => $request->user()->id,
                    ]);
                }

                if ($pointsClawedBack > 0) {
                    LoyaltyPointTransaction::create([
                        'customer_id' => $sale->customer_id,
                        'sale_id' => $sale->id,
                        'type' => 'adjustment',
                        'points' => -$pointsClawedBack,
                        'balance_after' => $customerBalanceAfter,
                        'note' => "Reversed for return {$returnNo}",
                    ]);
                }

                return $saleReturn;
            });
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $activityLogger->log(
            'sale.returned',
            "Return {$saleReturn->return_no} processed for Rs {$saleReturn->total_refunded} against sale {$saleReturn->sale->invoice_no}",
            $saleReturn,
            $request->user()->id
        );

        return redirect()->route('returns.show', $saleReturn)->with('success', 'Return processed successfully.');
    }

    public function show(Request $request, SaleReturn $saleReturn): View
    {
        $saleReturn->load('sale.customer', 'sale.cashier', 'processedBy', 'items.product');

        $view = $request->user()->role === 'admin' ? 'admin.returns.show' : 'cashier.returns.show';

        return view($view, [
            'saleReturn' => $saleReturn,
            'settings' => ReceiptSetting::current(),
        ]);
    }

    private function generateReturnNumber(): string
    {
        $prefix = 'RET-'.now()->format('Ymd').'-';

        do {
            $sequence = SaleReturn::where('return_no', 'like', $prefix.'%')->count() + 1;
            $candidate = $prefix.str_pad($sequence, 4, '0', STR_PAD_LEFT);
        } while (SaleReturn::where('return_no', $candidate)->exists());

        return $candidate;
    }
}
