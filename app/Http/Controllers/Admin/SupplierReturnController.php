<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class SupplierReturnController extends Controller
{
    public function __construct(private ActivityLogger $activityLogger) {}

    public function index(Request $request): View
    {
        $supplierReturns = SupplierReturn::query()
            ->with('supplier')
            ->withCount('items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('return_date')
            ->paginate(15);

        return view('admin.supplier-returns.index', compact('supplierReturns'));
    }

    public function create(Request $request): View
    {
        $suppliers = Supplier::orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'stock_qty']);

        $prefill = [
            'supplier_id' => $request->query('supplier_id'),
            'items' => $request->query('product_id')
                ? [[
                    'product_id' => (int) $request->query('product_id'),
                    'quantity' => (int) $request->query('quantity', 1),
                    'reason' => $request->query('reason', 'not_selling'),
                ]]
                : [],
        ];

        return view('admin.supplier-returns.create', compact('suppliers', 'products', 'prefill'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'return_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.reason' => ['required', 'in:expired,damaged,near_expiry,not_selling,wrong_item'],
        ]);

        foreach ($validated['items'] as $index => $item) {
            $product = Product::find($item['product_id']);

            if ($product && $item['quantity'] > $product->stock_qty) {
                return back()->withInput()->withErrors([
                    "items.{$index}.quantity" => "Cannot return {$item['quantity']} of {$product->name}; only {$product->stock_qty} in stock.",
                ]);
            }
        }

        $supplierReturn = DB::transaction(function () use ($validated, $request) {
            $supplierReturn = SupplierReturn::create([
                'supplier_id' => $validated['supplier_id'],
                'created_by' => $request->user()->id,
                'return_date' => $validated['return_date'],
                'status' => 'pending',
            ]);

            foreach ($validated['items'] as $item) {
                $supplierReturn->items()->create($item);
            }

            return $supplierReturn;
        });

        return redirect()->route('admin.supplier-returns.show', $supplierReturn)->with('success', 'Supplier return draft created.');
    }

    public function show(SupplierReturn $supplierReturn): View
    {
        $supplierReturn->load('supplier', 'creator', 'items.product');

        return view('admin.supplier-returns.show', compact('supplierReturn'));
    }

    /**
     * Locks the supplier return row (and re-checks its status inside the
     * transaction) so two concurrent "complete" clicks can't both pass the
     * pending-status check and double-decrement stock. Product rows are
     * locked too, and a full stock-sufficiency pass runs before any
     * decrement — mirrors PurchaseOrderController::markReceived() exactly.
     */
    public function complete(Request $request, SupplierReturn $supplierReturn): RedirectResponse
    {
        try {
            DB::transaction(function () use ($supplierReturn, $request) {
                $locked = SupplierReturn::where('id', $supplierReturn->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== 'pending') {
                    throw new RuntimeException('Only pending supplier returns can be completed.');
                }

                $items = $locked->items()->get();
                $lockedProducts = Product::whereIn('id', $items->pluck('product_id'))->lockForUpdate()->get()->keyBy('id');

                foreach ($items as $item) {
                    $product = $lockedProducts->get($item->product_id);

                    if (! $product || $product->stock_qty < $item->quantity) {
                        throw new RuntimeException("Insufficient stock for {$product?->name}: only {$product?->stock_qty} available.");
                    }
                }

                foreach ($items as $item) {
                    $lockedProducts->get($item->product_id)->decrement('stock_qty', $item->quantity);

                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'type' => 'out',
                        'quantity' => $item->quantity,
                        'reason' => 'supplier_return',
                        'recorded_by' => $request->user()->id,
                    ]);
                }

                $locked->update(['status' => 'completed']);
            });
        } catch (RuntimeException $e) {
            return redirect()->route('admin.supplier-returns.show', $supplierReturn)->with('error', $e->getMessage());
        }

        $this->activityLogger->log(
            'supplier_return.completed',
            "Completed supplier return #{$supplierReturn->id}",
            $supplierReturn
        );

        return redirect()->route('admin.supplier-returns.show', $supplierReturn)->with('success', 'Supplier return completed and stock updated.');
    }

    public function cancel(SupplierReturn $supplierReturn): RedirectResponse
    {
        try {
            DB::transaction(function () use ($supplierReturn) {
                $locked = SupplierReturn::where('id', $supplierReturn->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== 'pending') {
                    throw new RuntimeException('Only pending supplier returns can be cancelled.');
                }

                $locked->update(['status' => 'cancelled']);
            });
        } catch (RuntimeException $e) {
            return redirect()->route('admin.supplier-returns.show', $supplierReturn)->with('error', $e->getMessage());
        }

        $this->activityLogger->log(
            'supplier_return.cancelled',
            "Cancelled supplier return #{$supplierReturn->id}",
            $supplierReturn
        );

        return redirect()->route('admin.supplier-returns.show', $supplierReturn)->with('success', 'Supplier return cancelled.');
    }

    public function pdf(SupplierReturn $supplierReturn): Response
    {
        $supplierReturn->load('items.product', 'supplier', 'creator');

        $pdf = Pdf::loadView('supplier-returns.pdf', [
            'settings' => \App\Models\ReceiptSetting::current(),
            'supplierReturn' => $supplierReturn,
        ]);

        return $pdf->stream("supplier-return-{$supplierReturn->id}.pdf");
    }
}
