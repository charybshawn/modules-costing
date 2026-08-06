# Installing the Costing & Recipes module

A self-contained admin module for a Laravel + Vue/Inertia app that follows
the [admin module conventions](https://github.com/charybshawn/cultpantry-shop-front/blob/main/docs/ADMIN_MODULE_AUTHORING.md)
used by [Cult Pantry](https://github.com/charybshawn/cultpantry-shop-front) --
`packages/{vendor}/{module}/` layout, a `module.json` manifest, and
Composer/Inertia auto-discovery. It should work in any app built on that same
convention.

## 1. Clone this repo into your app

```bash
git clone https://github.com/charybshawn/modules-costing.git packages/cultpantry/costing
```

Your app's root `composer.json` needs a path repository covering
`packages/*/*` (Cult Pantry already has this by default):

```json
"repositories": [
    { "type": "path", "url": "packages/*/*" }
]
```

## 2. Install the package

```bash
composer require cultpantry/costing:@dev
```

This should also trigger `post-autoload-dump` -> `php artisan package:discover`
automatically if that's already wired into your root `composer.json` scripts.

Optional: if your app uses a sidebar icon map (Cult Pantry's
`resources/js/utils/adminNavIcons.ts`), add an entry for this module's `icon`
name from `module.json` -- otherwise it falls back to a generic icon.

## 2. Migrate and seed

```bash
php artisan migrate
php artisan db:seed --class="Cultpantry\\Costing\\Database\\Seeders\\CostingDatabaseSeeder"
```

The seeder loads the real data from the original Costing & Recipe Workbook:
17 ingredients, their full price history, current inventory, all 5 recipes,
and one production run (60 / 0 / 80 / 0 / 40 jars, matching the sheet).
Price History dates are stored as relative day-offsets from "today" rather
than fixed dates, so the 7-day pricing window behaves the same regardless of
when you actually run the seeder (see the seeder's docblock for details).

## 3. Publish the Vue pages and build

```bash
php artisan vendor:publish --tag=costing-pages
npm run build   # or npm run dev while iterating
```

## 4. Verify

Followed the authoring doc's checklist as closely as I could without a
runtime -- please actually run these, don't take my word for it:

```bash
php artisan route:list --name=admin.costing
php artisan package:discover --ansi
php artisan tinker --execute="dd(App\Support\AdminNav::all())"
php artisan test tests/Feature/Admin/CostingModuleAccessTest.php
php artisan test tests/Feature/Costing/CostingEngineTest.php
```

Then visit `/admin/settings/modules` as an admin -- Costing & Recipes should
show an "Active" badge. Toggle it off and confirm every `/admin/costing/*`
page 404s and the nav entry disappears; toggle it back on. Finally, log in
through an actual browser and click through Ingredients -> Price History ->
Inventory -> Recipes -> Production Planner -> Purchase Order once, since the
authoring doc is explicit that a green test suite alone isn't sufficient
proof (`actingAs()` bypasses real session middleware).

## What's in the module

- **Ingredients** -- catalogue + waste %/preferred source. Pricing columns
  ($/kg this week, effective $/kg, source, last price date, purchase unit)
  are calculated live from Price History, not stored -- same "auto" behavior
  as the original sheet, via `CalculateIngredientCosting`.
- **Price History** -- log every price you check. $/kg (or $/unit) is now
  calculated from Qty + Total Price instead of typed in, and returns blank
  instead of the original's `#DIV/0!` for incomplete rows.
- **Inventory** -- unit size x units on hand, auto-created for every
  ingredient.
- **Recipes** -- flavours with grams (or units) per jar per ingredient.
- **Production Planner** -- jar counts per flavour; the shopping list
  (Required / On Hand / To Purchase / Units to Buy / Est. Cost) is computed
  live via `CalculateProductionPlan`, with amber/green row highlighting.
- **Purchase Order** -- print-friendly view, filtered to only what needs
  buying, replacing the original tab 1:1 (including the original's exact
  "Nothing to buy -- inventory covers all requirements" message).

Everything is self-contained under `costing_*` tables -- no changes to your
existing `products`/`categories`/etc. schema.
