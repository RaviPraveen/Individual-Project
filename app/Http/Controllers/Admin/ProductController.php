<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()
            ->with('category')
            ->when($request->filled('name'), fn ($query) => $query->where('name', 'like', '%'.$request->input('name').'%'))
            ->when($request->filled('barcode'), fn ($query) => $query->where('barcode', 'like', '%'.$request->input('barcode').'%'))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->input('category_id')))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $categories = Category::orderBy('name')->get();

        return view('admin.products.index', compact('products', 'categories'));
    }

    public function create(): View
    {
        $categories = Category::orderBy('name')->get();

        return view('admin.products.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);
        $validated['is_active'] = $request->boolean('is_active', true);

        Product::create($validated);

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product): View
    {
        $categories = Category::orderBy('name')->get();

        return view('admin.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $this->validateProduct($request, $product);
        $validated['is_active'] = $request->boolean('is_active');

        $priceChanged = (float) $product->cost_price !== (float) $validated['cost_price']
            || (float) $product->selling_price !== (float) $validated['selling_price'];

        if ($priceChanged) {
            $activityLogger->log(
                'product.price_changed',
                "{$product->name}: cost {$product->cost_price} → {$validated['cost_price']}, ".
                    "selling price {$product->selling_price} → {$validated['selling_price']}",
                $product,
                $request->user()->id
            );
        }

        $product->update($validated);

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->saleItems()->exists()) {
            $product->update(['is_active' => false]);

            return redirect()->route('admin.products.index')->with('success', 'Product has sales history, so it was deactivated instead of deleted.');
        }

        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    public function adjustStock(Request $request, Product $product, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $newStock = $product->stock_qty + $validated['quantity'];

        if ($newStock < 0) {
            return redirect()->route('admin.products.index')->with('error', 'Stock adjustment would result in negative stock.');
        }

        $product->update(['stock_qty' => $newStock]);

        StockMovement::create([
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => $validated['quantity'],
            'reason' => $validated['reason'],
            'recorded_by' => $request->user()->id,
        ]);

        $activityLogger->log(
            'stock.adjusted',
            "{$product->name}: {$validated['quantity']} ({$validated['reason']}) — new stock {$newStock}",
            $product,
            $request->user()->id
        );

        return redirect()->route('admin.products.index')->with('success', 'Stock adjusted.');
    }

    public function importForm(): View
    {
        return view('admin.products.import');
    }

    /**
     * Bulk create/update products from a CSV (name, sku, category, cost
     * price, selling price, stock, reorder level). Matching is by SKU: an
     * existing SKU updates that product, a new one creates it. Unknown
     * category names are auto-created rather than skipped, since forcing
     * the admin to pre-create every category defeats the point of a bulk
     * import. Rows failing validation are skipped with a reason instead of
     * aborting the whole file.
     */
    public function import(Request $request): View
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $handle = fopen($request->file('csv_file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim($h)), fgetcsv($handle));
        $columnIndex = array_flip($header);

        $required = ['name', 'sku', 'category', 'cost price', 'selling price', 'stock', 'reorder level'];
        $missingColumns = array_diff($required, $header);

        if (! empty($missingColumns)) {
            fclose($handle);

            return view('admin.products.import', [
                'summary' => null,
                'headerError' => 'CSV is missing required column(s): '.implode(', ', $missingColumns),
            ]);
        }

        $created = 0;
        $updated = 0;
        $skipped = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $data = [
                'name' => trim($row[$columnIndex['name']] ?? ''),
                'sku' => trim($row[$columnIndex['sku']] ?? ''),
                'category' => trim($row[$columnIndex['category']] ?? ''),
                'cost_price' => trim($row[$columnIndex['cost price']] ?? ''),
                'selling_price' => trim($row[$columnIndex['selling price']] ?? ''),
                'stock_qty' => trim($row[$columnIndex['stock']] ?? ''),
                'reorder_level' => trim($row[$columnIndex['reorder level']] ?? ''),
            ];

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'sku' => ['required', 'string', 'max:255'],
                'cost_price' => ['required', 'numeric', 'min:0'],
                'selling_price' => ['required', 'numeric', 'min:0'],
                'stock_qty' => ['required', 'integer', 'min:0'],
                'reorder_level' => ['required', 'integer', 'min:0'],
            ]);

            if ($validator->fails()) {
                $skipped[] = [
                    'row' => $rowNumber,
                    'sku' => $data['sku'] !== '' ? $data['sku'] : '(none)',
                    'reason' => implode(' ', $validator->errors()->all()),
                ];

                continue;
            }

            $categoryId = null;

            if ($data['category'] !== '') {
                $categoryId = Category::whereRaw('LOWER(name) = ?', [strtolower($data['category'])])
                    ->value('id')
                    ?? Category::create(['name' => $data['category']])->id;
            }

            $payload = [
                'name' => $data['name'],
                'category_id' => $categoryId,
                'cost_price' => $data['cost_price'],
                'selling_price' => $data['selling_price'],
                'stock_qty' => $data['stock_qty'],
                'reorder_level' => $data['reorder_level'],
            ];

            $existing = Product::where('sku', $data['sku'])->first();

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                Product::create($payload + [
                    'sku' => $data['sku'],
                    'unit' => 'pcs',
                    'is_active' => true,
                ]);
                $created++;
            }
        }

        fclose($handle);

        return view('admin.products.import', [
            'summary' => [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $term = $request->query('q', '');

        $products = Product::query()
            ->where('is_active', true)
            ->when($term !== '', function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'sku', 'barcode', 'selling_price', 'stock_qty']);

        return response()->json($products);
    }

    private function validateProduct(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku,'.($product?->id)],
            'barcode' => ['nullable', 'string', 'max:255', 'unique:products,barcode,'.($product?->id)],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'stock_qty' => ['required', 'integer', 'min:0'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'expiry_date' => ['nullable', 'date'],
            'unit' => ['required', 'string', 'max:50'],
        ]);
    }
}
