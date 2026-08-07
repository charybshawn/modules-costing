<?php

namespace Cultpantry\Costing;

use App\Support\AdminNav;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\InventoryItem;
use Cultpantry\Costing\Models\KitchenRental;
use Cultpantry\Costing\Models\PriceHistoryEntry;
use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;
use Cultpantry\Costing\Policies\IngredientPolicy;
use Cultpantry\Costing\Policies\InventoryItemPolicy;
use Cultpantry\Costing\Policies\KitchenRentalPolicy;
use Cultpantry\Costing\Policies\PriceHistoryEntryPolicy;
use Cultpantry\Costing\Policies\ProductionRunPolicy;
use Cultpantry\Costing\Policies\RecipePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class CostingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 1. Admin routes -- additive merge into the existing admin route
        //    group, since prefix/name/middleware are identical.
        $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');

        // 2. Migrations for this module's own tables (costing_*). Not part
        //    of the minimal example-admin-module, but the standard Laravel
        //    package convention for a module that needs real persistence.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // 3. Nav entry. 'match' covers every costing/* sub-page so the one
        //    sidebar entry stays highlighted across Ingredients, Price
        //    History, Inventory, Recipes, and the Production Planner.
        AdminNav::register([
            'name' => 'Costing & Recipes',
            'href' => '/admin/costing',
            'icon' => 'costing',
            'match' => '/admin/costing',
            'module' => 'cultpantry/costing',
        ]);

        // 4. Policies -- explicit registration. Laravel's naming-convention
        //    auto-discovery only scans App\Models -> App\Policies, not
        //    module namespaces.
        Gate::policy(Ingredient::class, IngredientPolicy::class);
        Gate::policy(PriceHistoryEntry::class, PriceHistoryEntryPolicy::class);
        Gate::policy(InventoryItem::class, InventoryItemPolicy::class);
        Gate::policy(Recipe::class, RecipePolicy::class);
        Gate::policy(ProductionRun::class, ProductionRunPolicy::class);
        Gate::policy(KitchenRental::class, KitchenRentalPolicy::class);

        // 5. Publish the module's raw Vue source into
        //    resources/js/Pages/Vendor/costing/ -- inside the SAME root the
        //    core Inertia glob and Inertia's testing view-finder already
        //    scan. Run via:
        //    php artisan vendor:publish --tag=costing-pages
        $this->publishes([
            __DIR__.'/../resources/js/Pages' => resource_path('js/Pages/Vendor/costing'),
        ], 'costing-pages');
    }
}
