<?php

namespace Cultpantry\Costing\Http\Controllers\Admin;

use App\Actions\GetSiteSetting;
use App\Http\Controllers\Controller;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\PackageSize;
use Cultpantry\Costing\Models\PriceHistoryEntry;
use Cultpantry\Costing\Support\CostingBreadcrumbs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class PriceHistoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_unless($request->user()?->isAdmin(), 403, 'Admin access required.');
                return $next($request);
            }),
            new Middleware(function ($request, $next) {
                abort_unless(app(GetSiteSetting::class)->handle('modules.cultpantry/costing.enabled', true), 404);
                return $next($request);
            }),
        ];
    }

    public function index(): Response
    {
        $this->authorize('viewAny', PriceHistoryEntry::class);

        $allEntries = PriceHistoryEntry::with('ingredient:id,name,unit_type')
            ->orderByDesc('purchased_at')
            ->get();

        $cutoff = now()->subDays(7)->startOfDay();

        // A row "needs an update" only if it's the *latest* logged price for
        // its ingredient/wholesaler/brand combination and that latest price
        // is stale -- older entries superseded by a newer one for the same
        // combination don't need anything, they're just history.
        $latestIdPerSource = $allEntries
            ->sortByDesc(fn (PriceHistoryEntry $entry) => sprintf('%010d-%010d', $entry->purchased_at?->timestamp ?? 0, $entry->id))
            ->groupBy(fn (PriceHistoryEntry $entry) => $entry->ingredient_id.'||'.$entry->provider.'||'.($entry->brand ?? ''))
            ->map(fn ($group) => $group->first()->id);

        $entries = $allEntries->map(fn (PriceHistoryEntry $entry) => [
            'id' => $entry->id,
            'ingredient_id' => $entry->ingredient_id,
            'ingredient_name' => $entry->ingredient?->name,
            'unit_type' => $entry->ingredient?->unit_type,
            'purchased_at' => optional($entry->purchased_at)->format('Y-m-d'),
            'provider' => $entry->provider,
            'brand' => $entry->brand,
            'qty' => $entry->qty ? (float) $entry->qty : null,
            'total_price' => $entry->total_price ? (float) $entry->total_price : null,
            'sku' => $entry->sku,
            'notes' => $entry->notes,
            'price_per_unit' => $entry->price_per_unit,
            'price_per_100g' => $entry->price_per_100g,
            'needs_update' => $latestIdPerSource->contains($entry->id)
                && ($entry->purchased_at === null || $entry->purchased_at->lt($cutoff)),
        ]);

        return Inertia::render('Vendor/costing/PriceHistory/Index', [
            'entries' => $entries,
            'breadcrumbs' => CostingBreadcrumbs::trail(['label' => 'Price History']),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PriceHistoryEntry::class);

        $clone = null;

        if ($cloneId = $request->query('clone')) {
            $source = PriceHistoryEntry::find($cloneId);

            if ($source) {
                $this->authorize('view', $source);

                $clone = [
                    'ingredient_id' => $source->ingredient_id,
                    'package_size_id' => $source->package_size_id,
                    'qty' => $source->qty ? (float) $source->qty : null,
                    'total_price' => $source->total_price ? (float) $source->total_price : null,
                    'sku' => $source->sku,
                    'notes' => $source->notes,
                ];
            }
        }

        return Inertia::render('Vendor/costing/PriceHistory/Create', [
            'ingredients' => Ingredient::orderBy('name')->get(['id', 'name', 'unit_type']),
            'clone' => $clone,
            // Lets the Ingredients page's "Available Prices" modal (and its
            // "Log a new price" link, shown when an ingredient has none yet)
            // land here with the right ingredient already picked.
            'preselectIngredientId' => $request->integer('ingredient') ?: null,
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'Price History', 'href' => route('admin.costing.price-history.index')],
                ['label' => 'Log a Price'],
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PriceHistoryEntry::class);

        $validated = $this->validated($request);

        PriceHistoryEntry::create($this->withSourceSnapshot($validated));

        // Normally lands on the index -- this is the full Log a Price page's
        // primary submit action. But AvailablePricesModal also posts here
        // directly (its "+ Add a new source" flow), and wants to stay right
        // where it is rather than navigating away, same reasoning as
        // updatePrice()/setPreferred()/setPackageSize() below.
        if ($request->boolean('stay')) {
            return redirect()->back()->with('success', 'Price logged.');
        }

        return redirect()
            ->route('admin.costing.price-history.index')
            ->with('success', 'Price logged.');
    }

    public function edit(PriceHistoryEntry $priceHistoryEntry): Response
    {
        $this->authorize('update', $priceHistoryEntry);

        return Inertia::render('Vendor/costing/PriceHistory/Edit', [
            'entry' => [
                'id' => $priceHistoryEntry->id,
                'ingredient_id' => $priceHistoryEntry->ingredient_id,
                'package_size_id' => $priceHistoryEntry->package_size_id,
                'purchased_at' => optional($priceHistoryEntry->purchased_at)->format('Y-m-d'),
                'qty' => $priceHistoryEntry->qty ? (float) $priceHistoryEntry->qty : null,
                'total_price' => $priceHistoryEntry->total_price ? (float) $priceHistoryEntry->total_price : null,
                'sku' => $priceHistoryEntry->sku,
                'notes' => $priceHistoryEntry->notes,
            ],
            'ingredients' => Ingredient::orderBy('name')->get(['id', 'name', 'unit_type']),
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'Price History', 'href' => route('admin.costing.price-history.index')],
                ['label' => 'Edit Entry'],
            ),
        ]);
    }

    public function update(Request $request, PriceHistoryEntry $priceHistoryEntry): RedirectResponse
    {
        $this->authorize('update', $priceHistoryEntry);

        $validated = $this->validated($request);

        $priceHistoryEntry->update($this->withSourceSnapshot($validated));

        return redirect()
            ->route('admin.costing.price-history.index')
            ->with('success', 'Price entry updated.');
    }

    /**
     * Quick re-log: same ingredient/wholesaler/brand/qty/sku/notes as
     * $priceHistoryEntry, dated today, with just a new price -- for the
     * common case of rechecking a price you already log regularly.
     */
    public function updatePrice(Request $request, PriceHistoryEntry $priceHistoryEntry): RedirectResponse
    {
        $this->authorize('view', $priceHistoryEntry);
        $this->authorize('create', PriceHistoryEntry::class);

        $validated = $request->validate([
            'total_price' => ['required', 'numeric', 'min:0'],
        ]);

        PriceHistoryEntry::create([
            'ingredient_id' => $priceHistoryEntry->ingredient_id,
            'package_size_id' => $priceHistoryEntry->package_size_id,
            'purchased_at' => now(),
            'provider' => $priceHistoryEntry->provider,
            'brand' => $priceHistoryEntry->brand,
            'qty' => $priceHistoryEntry->qty,
            'total_price' => $validated['total_price'],
            'sku' => $priceHistoryEntry->sku,
            'notes' => $priceHistoryEntry->notes,
        ]);

        // Not a hardcoded route -- this action is reused from both the
        // Price History page and the Ingredients "Available Prices" modal,
        // and should reload whichever one the request actually came from.
        return redirect()->back()->with('success', 'Price updated.');
    }

    public function destroy(PriceHistoryEntry $priceHistoryEntry): RedirectResponse
    {
        $this->authorize('delete', $priceHistoryEntry);

        $priceHistoryEntry->delete();

        return redirect()
            ->route('admin.costing.price-history.index')
            ->with('success', 'Price entry deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'ingredient_id' => ['required', 'exists:costing_ingredients,id'],
            'package_size_id' => ['required', 'exists:costing_ingredient_package_sizes,id'],
            'purchased_at' => ['nullable', 'date'],
            'qty' => ['nullable', 'numeric', 'min:0'],
            'total_price' => ['nullable', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * provider/brand are a snapshot of the linked Source at write time (see
     * PriceHistoryEntry::packageSize()) -- never typed directly, always
     * copied from the Source the entry actually points to.
     */
    private function withSourceSnapshot(array $validated): array
    {
        $packageSize = PackageSize::findOrFail($validated['package_size_id']);

        return [
            ...$validated,
            'provider' => $packageSize->provider,
            'brand' => $packageSize->brand,
        ];
    }
}
