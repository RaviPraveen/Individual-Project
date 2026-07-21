<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\PromotionRecommendationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function index(Request $request, PromotionRecommendationService $recommendations): View
    {
        Promotion::syncDueStatuses();

        $query = Promotion::query()->with('product');

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        $filter = $request->input('filter', 'all');
        if ($filter === 'featured') {
            $query->where('is_featured', true);
        } elseif (in_array($filter, [Promotion::STATUS_ACTIVE, Promotion::STATUS_SCHEDULED, Promotion::STATUS_PAUSED, Promotion::STATUS_EXPIRED], true)) {
            $query->where('status', $filter);
        }

        $sort = $request->input('sort', 'newest');
        match ($sort) {
            'oldest' => $query->orderBy('created_at'),
            'priority' => $query->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'normal' THEN 2 WHEN 'low' THEN 3 ELSE 4 END"),
            'duration' => $query->orderByDesc('display_duration'),
            'start_date' => $query->orderBy('start_date'),
            'end_date' => $query->orderBy('end_date'),
            'offer_price' => $query->orderBy('offer_price'),
            default => $query->orderByDesc('created_at'),
        };

        $promotions = $query->paginate(15)->withQueryString();

        return view('admin.promotions.index', [
            'promotions' => $promotions,
            'search' => $search,
            'filter' => $filter,
            'sort' => $sort,
            'counts' => [
                'total' => Promotion::count(),
                'active' => Promotion::where('status', Promotion::STATUS_ACTIVE)->count(),
                'scheduled' => Promotion::where('status', Promotion::STATUS_SCHEDULED)->count(),
                'paused' => Promotion::where('status', Promotion::STATUS_PAUSED)->count(),
                'expired' => Promotion::where('status', Promotion::STATUS_EXPIRED)->count(),
                'featured' => Promotion::where('is_featured', true)->count(),
            ],
            'recommendations' => $recommendations->recommendations($request->user()->id),
        ]);
    }

    public function create(): View
    {
        return view('admin.promotions.create', [
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'selling_price', 'category_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePromotion($request);

        $product = Product::findOrFail($validated['product_id']);

        $promotion = new Promotion($validated);
        $promotion->current_price = $product->selling_price;
        $promotion->refreshDiscountPercentage();
        $promotion->status = $promotion->dateDerivedStatus();
        $promotion->created_by = $request->user()->id;

        if ($request->hasFile('poster_image')) {
            $promotion->poster_path = $request->file('poster_image')->store('promotions', 'public');
            $promotion->poster_source = 'custom';
        }

        $promotion->save();

        return redirect()->route('admin.promotions.index')->with('success', __('Promotion ":title" created.', ['title' => $promotion->title]));
    }

    public function edit(Promotion $promotion): View
    {
        return view('admin.promotions.edit', [
            'promotion' => $promotion,
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'selling_price', 'category_id']),
        ]);
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $validated = $this->validatePromotion($request, $promotion);

        $product = Product::findOrFail($validated['product_id']);

        $promotion->fill($validated);
        $promotion->current_price = $product->selling_price;
        $promotion->refreshDiscountPercentage();

        if ($request->hasFile('poster_image')) {
            if ($promotion->poster_path) {
                Storage::disk('public')->delete($promotion->poster_path);
            }
            $promotion->poster_path = $request->file('poster_image')->store('promotions', 'public');
            $promotion->poster_source = 'custom';
        }

        $promotion->save();

        return redirect()->route('admin.promotions.index')->with('success', __('Promotion ":title" updated.', ['title' => $promotion->title]));
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        if ($promotion->poster_path) {
            Storage::disk('public')->delete($promotion->poster_path);
        }
        if ($promotion->pending_poster_path && $promotion->pending_poster_path !== $promotion->poster_path) {
            Storage::disk('public')->delete($promotion->pending_poster_path);
        }

        $title = $promotion->title;
        $promotion->delete();

        return redirect()->route('admin.promotions.index')->with('success', __('Promotion ":title" deleted.', ['title' => $title]));
    }

    /**
     * Manual ON/OFF toggle from the list page. OFF always pauses; ON resumes
     * respecting the schedule (scheduled/active) unless the window has
     * already closed, in which case it's rejected with the message the spec
     * calls for instead of silently reactivating an expired promotion.
     */
    public function toggleStatus(Promotion $promotion): RedirectResponse
    {
        if ($promotion->status === Promotion::STATUS_PAUSED) {
            if (! $promotion->canActivate()) {
                return back()->with('error', __('This promotion has expired. Please update the End Date before activating.'));
            }

            $promotion->activate();

            return back()->with('success', __('Promotion ":title" activated.', ['title' => $promotion->title]));
        }

        $promotion->pause();

        return back()->with('success', __('Promotion ":title" paused.', ['title' => $promotion->title]));
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:promotions,id'],
            'bulk_action' => ['required', 'in:pause,activate,delete'],
        ]);

        $promotions = Promotion::whereIn('id', $validated['ids'])->get();

        foreach ($promotions as $promotion) {
            match ($validated['bulk_action']) {
                'pause' => $promotion->pause(),
                'activate' => $promotion->canActivate() ? $promotion->activate() : null,
                'delete' => tap($promotion, function ($p) {
                    if ($p->poster_path) {
                        Storage::disk('public')->delete($p->poster_path);
                    }
                    if ($p->pending_poster_path && $p->pending_poster_path !== $p->poster_path) {
                        Storage::disk('public')->delete($p->pending_poster_path);
                    }
                })->delete(),
            };
        }

        return back()->with('success', __(':count promotion(s) updated.', ['count' => $promotions->count()]));
    }

    private function validatePromotion(Request $request, ?Promotion $promotion = null): array
    {
        $product = Product::find($request->input('product_id'));

        $offerPriceRules = ['required', 'numeric', 'min:0'];
        if ($product) {
            $offerPriceRules[] = 'max:'.$product->selling_price;
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'product_id' => ['required', 'exists:products,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'offer_price' => $offerPriceRules,
            'poster_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:10240'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'display_duration' => ['required', 'integer', 'min:5', 'max:300'],
            'priority' => ['required', 'in:high,normal,low'],
            'is_featured' => ['nullable', 'boolean'],
            'target_screen' => ['required', 'in:customer_display,dashboard_banner,both'],
        ]);

        $validated['is_featured'] = $request->boolean('is_featured');

        return $validated;
    }
}
