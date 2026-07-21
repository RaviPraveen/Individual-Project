<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class PurchaseOrderController extends Controller
{
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

    public function markReceived(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== 'pending') {
            return redirect()->route('admin.purchase-orders.show', $purchaseOrder)
                ->with('error', 'Only pending purchase orders can be marked received.');
        }

        DB::transaction(function () use ($purchaseOrder, $request) {
            foreach ($purchaseOrder->items()->with('product')->get() as $item) {
                $item->product->increment('stock_qty', $item->quantity);

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'type' => 'in',
                    'quantity' => $item->quantity,
                    'reason' => 'purchase',
                    'recorded_by' => $request->user()->id,
                ]);
            }

            $purchaseOrder->update(['status' => 'received']);
        });

        return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order marked received and stock updated.');
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== 'pending') {
            return redirect()->route('admin.purchase-orders.show', $purchaseOrder)
                ->with('error', 'Only pending purchase orders can be cancelled.');
        }

        $purchaseOrder->update(['status' => 'cancelled']);

        return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order cancelled.');
    }
}
