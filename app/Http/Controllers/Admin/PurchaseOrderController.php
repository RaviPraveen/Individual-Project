<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class PurchaseOrderController extends Controller
{
    public function __construct(private ActivityLogger $activityLogger) {}

    public function index(): View
    {
        $purchaseOrders = PurchaseOrder::with('supplier')
            ->orderByDesc('order_date')
            ->paginate(15);

        return view('admin.purchase-orders.index', compact('purchaseOrders'));
    }

    public function create(Request $request): View
    {
        $suppliers = Supplier::orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();

        // Optional prefill from the Smart Reorder Assistant, passed as a
        // query string (?supplier_id=..&items[0][product_id]=..&items[0][quantity]=..).
        $prefill = [
            'supplier_id' => $request->query('supplier_id'),
            'items' => collect($request->query('items', []))
                ->map(fn ($item) => [
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                ])
                ->filter(fn ($item) => $item['product_id'] > 0)
                ->values()
                ->all(),
        ];

        return view('admin.purchase-orders.create', compact('suppliers', 'products', 'prefill'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'order_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $total = collect($validated['items'])->sum(fn ($item) => $item['quantity'] * $item['unit_cost']);

            $purchaseOrder = PurchaseOrder::create([
                'supplier_id' => $validated['supplier_id'],
                'created_by' => $request->user()->id,
                'order_date' => $validated['order_date'],
                'status' => 'pending',
                'total_amount' => $total,
            ]);

            foreach ($validated['items'] as $item) {
                $purchaseOrder->items()->create($item);
            }
        });

        return redirect()->route('admin.purchase-orders.index')->with('success', 'Purchase order created.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load('supplier', 'creator', 'items.product');

        return view('admin.purchase-orders.show', compact('purchaseOrder'));
    }

    /**
     * Locks the purchase order row (and re-checks its status inside the
     * transaction) so two concurrent "mark received" clicks on the same PO
     * can't both pass the pending-status check and double-restock it. The
     * product rows are locked too before incrementing, so a receive can't
     * read stale stock alongside a concurrent sale/return/other receipt on
     * the same product.
     */
    public function markReceived(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            DB::transaction(function () use ($purchaseOrder, $request) {
                $locked = PurchaseOrder::where('id', $purchaseOrder->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== 'pending') {
                    throw new RuntimeException('Only pending purchase orders can be marked received.');
                }

                $items = $locked->items()->get();
                $lockedProducts = Product::whereIn('id', $items->pluck('product_id'))->lockForUpdate()->get()->keyBy('id');

                foreach ($items as $item) {
                    $lockedProducts->get($item->product_id)?->increment('stock_qty', $item->quantity);

                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'type' => 'in',
                        'quantity' => $item->quantity,
                        'reason' => 'purchase',
                        'recorded_by' => $request->user()->id,
                    ]);
                }

                $locked->update(['status' => 'received']);
            });
        } catch (RuntimeException $e) {
            return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('error', $e->getMessage());
        }

        $this->activityLogger->log(
            'purchase_order.received',
            "Marked purchase order #{$purchaseOrder->id} as received",
            $purchaseOrder
        );

        return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order marked received and stock updated.');
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            DB::transaction(function () use ($purchaseOrder) {
                $locked = PurchaseOrder::where('id', $purchaseOrder->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== 'pending') {
                    throw new RuntimeException('Only pending purchase orders can be cancelled.');
                }

                $locked->update(['status' => 'cancelled']);
            });
        } catch (RuntimeException $e) {
            return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order cancelled.');
    }
}
