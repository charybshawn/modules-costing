<?php

namespace Cultpantry\Costing\Actions;

use App\Actions\GetSiteSetting;

/**
 * The short facility/brand code (e.g. "CP") stamped onto every
 * auto-generated production run batch code -- see GenerateBatchCode.
 * Same Setting-store pattern as GetPriceStalenessDays, configurable from
 * the module's own Settings page. Null/blank means no prefix has been set
 * yet, in which case GenerateBatchCode falls back to a prefix-less code
 * rather than blocking run creation on it.
 */
class GetBatchCodePrefix
{
    public const SETTING_KEY = 'costing.batch_code_prefix';

    public function handle(): ?string
    {
        $value = app(GetSiteSetting::class)->handle(self::SETTING_KEY, null);

        return $value !== null && $value !== '' ? $value : null;
    }
}
