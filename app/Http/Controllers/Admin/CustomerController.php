<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiLog;
use App\Models\Customer;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        $customers = Customer::orderBy('name')->paginate(15);

        return view('admin.customers.index', compact('customers'));
    }

    public function create(): View
    {
        return view('admin.customers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCustomer($request);

        Customer::create($validated);

        return redirect()->route('admin.customers.index')->with('success', 'Customer created.');
    }

    public function edit(Customer $customer): View
    {
        return view('admin.customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $this->validateCustomer($request);

        $customer->update($validated);

        return redirect()->route('admin.customers.index')->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->sales()->exists()) {
            return redirect()->route('admin.customers.index')
                ->with('error', 'Cannot delete a customer that has sales history.');
        }

        $customer->delete();

        return redirect()->route('admin.customers.index')->with('success', 'Customer deleted.');
    }

    public function behavior(Request $request, Customer $customer, AiService $gemini): View
    {
        $totalOrders = $customer->sales()->count();
        $totalSpend = (float) $customer->sales()->sum('total');
        $avgBasket = $totalOrders > 0 ? round($totalSpend / $totalOrders, 2) : 0;
        $firstPurchase = $customer->sales()->min('created_at');
        $lastPurchase = $customer->sales()->max('created_at');

        $topCategories = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.customer_id', $customer->id)
            ->selectRaw("COALESCE(categories.name, 'Uncategorized') as name, SUM(sale_items.quantity) as qty")
            ->groupByRaw("COALESCE(categories.name, 'Uncategorized')")
            ->orderByDesc('qty')
            ->limit(3)
            ->get();

        $summary = [
            'total_orders' => $totalOrders,
            'total_spend' => $totalSpend,
            'avg_basket' => $avgBasket,
            'first_purchase' => $firstPurchase,
            'last_purchase' => $lastPurchase,
            'top_categories' => $topCategories,
        ];

        $narrative = null;

        if ($totalOrders > 0) {
            $categoryList = $topCategories->map(fn ($c) => "{$c->name} ({$c->qty} items)")->implode(', ') ?: 'none recorded';

            $prompt = "You are a retail analyst for a Sri Lankan supermarket called Welcome Foodcity. ".
                "Based ONLY on the following computed customer data, write a short (2-3 sentence) plain-language ".
                "summary of this customer's buying pattern. Do not invent any numbers beyond what is given.\n\n".
                "Customer: {$customer->name}\n".
                "Total orders: {$totalOrders}\n".
                "Total spend: {$totalSpend}\n".
                "Average basket value: {$avgBasket}\n".
                "First purchase: {$firstPurchase}\n".
                "Last purchase: {$lastPurchase}\n".
                "Top categories purchased: {$categoryList}";

            $narrative = $gemini->generate($prompt);

            AiLog::create([
                'user_id' => $request->user()->id,
                'query' => "Customer behavior analysis for customer #{$customer->id} ({$customer->name})",
                'response' => $narrative ?? '[AI unavailable — narrative not generated]',
            ]);
        }

        return view('admin.customers.behavior', compact('customer', 'summary', 'narrative'));
    }

    public function search(Request $request): JsonResponse
    {
        $term = $request->query('q', '');

        $customers = Customer::query()
            ->when($term !== '', function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'phone', 'points_balance']);

        return response()->json($customers);
    }

    private function validateCustomer(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
