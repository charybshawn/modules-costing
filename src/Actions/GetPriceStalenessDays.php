<?php

namespace Cultpantry\Costing\Actions;

use App\Actions\GetSiteSetting;

/**
 * Single source of truth for "how many days back does a logged price still
 * count as current" -- read by CalculateIngredientCosting (what recipe/
 * production costing trusts), GetIngredientPriceOptions (Sources table
 * staleness) and PriceHistoryController (the "needs update" flag), so all
 * three always agree instead of drifting if the window were hardcoded in
 * each place separately. Backed by the app's generic Setting key/value
 * store under a costing-namespaced key, configurable from the module's own
 * Settings page.
 */
class GetPriceStalenessDays
{
    public const SETTING_KEY = 'costing.price_staleness_days';

    public const DEFAULT_DAYS = 7;

    public function handle(): int
    {
        return (int) app(GetSiteSetting::class)->handle(self::SETTING_KEY, self::DEFAULT_DAYS);
    }
}
