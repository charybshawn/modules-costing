<?php

namespace Cultpantry\Costing\Support;

/**
 * AdminLayout.vue auto-generates breadcrumbs by concatenating URL segments
 * and assuming every intermediate path is a real page (works fine for core
 * admin sections, which all have a bare index route and a bare show route
 * per resource). This module doesn't follow that shape -- there's no bare
 * /admin/costing route, and Ingredient/Recipe/PriceHistoryEntry/Inventory
 * have no show() route, only edit() -- so the auto-generated links 404.
 *
 * Every costing controller passes an explicit 'breadcrumbs' prop instead.
 * Inertia's Vue3 adapter spreads all page props onto the persistent layout
 * component (h(layout, { ...page.props }, ...)), so AdminLayout's existing
 * `breadcrumbs` prop picks this up automatically -- no Vue changes needed.
 */
class CostingBreadcrumbs
{
    /**
     * @param array{label: string, href?: string} ...$crumbs
     * @return array<int, array{label: string, href?: string}>
     */
    public static function trail(array ...$crumbs): array
    {
        return [
            ['label' => 'Costing & Recipes', 'href' => route('admin.costing.ingredients.index')],
            ...$crumbs,
        ];
    }
}
