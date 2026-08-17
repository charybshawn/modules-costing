<?php

use Cultpantry\Costing\Http\Controllers\Admin\DashboardController;
use Cultpantry\Costing\Http\Controllers\Admin\IngredientController;
use Cultpantry\Costing\Http\Controllers\Admin\InventoryController;
use Cultpantry\Costing\Http\Controllers\Admin\KitchenRentalController;
use Cultpantry\Costing\Http\Controllers\Admin\PriceHistoryController;
use Cultpantry\Costing\Http\Controllers\Admin\ProductionPlannerController;
use Cultpantry\Costing\Http\Controllers\Admin\RecipeController;
use Illuminate\Support\Facades\Route;

// 'web' is REQUIRED here and is not optional. Core routes/web.php gets the
// 'web' middleware group automatically via bootstrap/app.php's
// withRouting(web: ...); routes loaded via loadRoutesFrom() from a provider
// do NOT get it automatically. Omitting it means no session is started, so
// 'auth' silently treats every request as a guest and redirects to /login --
// even for a logged-in admin. Do not drop 'web' from this array.
Route::prefix('admin')->name('admin.')->middleware(['web', 'auth', 'admin'])->group(function () {
    Route::prefix('costing')->name('costing.')->group(function () {

        Route::get('/', [DashboardController::class, 'index'])->name('index');

        Route::prefix('ingredients')->name('ingredients.')->group(function () {
            Route::get('/', [IngredientController::class, 'index'])->name('index');
            Route::get('create', [IngredientController::class, 'create'])->name('create');
            Route::post('/', [IngredientController::class, 'store'])->name('store');
            Route::get('{ingredient}/edit', [IngredientController::class, 'edit'])->name('edit');
            Route::put('{ingredient}', [IngredientController::class, 'update'])->name('update');
            Route::get('{ingredient}/price-options', [IngredientController::class, 'priceOptions'])->name('price-options');
            Route::post('{ingredient}/preferred', [IngredientController::class, 'setPreferred'])->name('set-preferred');
            Route::post('{ingredient}/package-size', [IngredientController::class, 'setPackageSize'])->name('set-package-size');
            Route::post('{ingredient}/sources/{packageSize}/rename', [IngredientController::class, 'renameSource'])->name('sources.rename');
            Route::delete('{ingredient}', [IngredientController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('price-history')->name('price-history.')->group(function () {
            Route::get('/', [PriceHistoryController::class, 'index'])->name('index');
            Route::get('create', [PriceHistoryController::class, 'create'])->name('create');
            Route::post('/', [PriceHistoryController::class, 'store'])->name('store');
            Route::get('{priceHistoryEntry}/edit', [PriceHistoryController::class, 'edit'])->name('edit');
            Route::put('{priceHistoryEntry}', [PriceHistoryController::class, 'update'])->name('update');
            Route::post('{priceHistoryEntry}/update-price', [PriceHistoryController::class, 'updatePrice'])->name('update-price');
            Route::delete('{priceHistoryEntry}', [PriceHistoryController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/', [InventoryController::class, 'index'])->name('index');
            Route::post('items', [InventoryController::class, 'storeItem'])->name('items.store');
            Route::post('bulk-update', [InventoryController::class, 'bulkUpdate'])->name('bulk-update');
            Route::get('adjustments', [InventoryController::class, 'adjustments'])->name('adjustments');
            Route::get('{ingredient}/sources', [InventoryController::class, 'sources'])->name('sources');
            Route::post('{ingredient}/sources/{packageSize}', [InventoryController::class, 'adjustSource'])->name('sources.adjust');
            Route::delete('{ingredient}/sources/{packageSize}', [InventoryController::class, 'destroySource'])->name('sources.destroy');
        });

        Route::prefix('recipes')->name('recipes.')->group(function () {
            Route::get('/', [RecipeController::class, 'index'])->name('index');
            Route::get('grid', [RecipeController::class, 'grid'])->name('grid');
            Route::get('costing', [RecipeController::class, 'costing'])->name('costing');
            Route::get('cost-history', [RecipeController::class, 'costHistory'])->name('cost-history');
            Route::get('create', [RecipeController::class, 'create'])->name('create');
            Route::post('/', [RecipeController::class, 'store'])->name('store');
            Route::get('{recipe}/edit', [RecipeController::class, 'edit'])->name('edit');
            Route::put('{recipe}', [RecipeController::class, 'update'])->name('update');
            Route::put('{recipe}/costing', [RecipeController::class, 'updateCosting'])->name('update-costing');
            Route::delete('{recipe}', [RecipeController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('production-planner')->name('production-planner.')->group(function () {
            Route::get('runs', [ProductionPlannerController::class, 'runs'])->name('runs');
            Route::get('{productionRun}', [ProductionPlannerController::class, 'show'])->name('show');
            Route::put('{productionRun}', [ProductionPlannerController::class, 'update'])->name('update');
            Route::delete('{productionRun}', [ProductionPlannerController::class, 'destroy'])->name('destroy');
            Route::post('{productionRun}/complete', [ProductionPlannerController::class, 'complete'])->name('complete');
            Route::post('{productionRun}/uncomplete', [ProductionPlannerController::class, 'uncomplete'])->name('uncomplete');
            Route::get('{productionRun}/purchase-order', [ProductionPlannerController::class, 'purchaseOrder'])->name('purchase-order');
        });

        Route::prefix('kitchen-rentals')->name('kitchen-rentals.')->group(function () {
            Route::get('/', [KitchenRentalController::class, 'index'])->name('index');
            Route::post('import', [KitchenRentalController::class, 'import'])->name('import');
            Route::post('{kitchenRental}/create-run', [KitchenRentalController::class, 'createRun'])->name('create-run');
            Route::post('{kitchenRental}/attach-run', [KitchenRentalController::class, 'attachRun'])->name('attach-run');
            Route::post('{kitchenRental}/detach-run', [KitchenRentalController::class, 'detachRun'])->name('detach-run');
            Route::post('{kitchenRental}/status', [KitchenRentalController::class, 'updateStatus'])->name('update-status');
        });
    });
});
