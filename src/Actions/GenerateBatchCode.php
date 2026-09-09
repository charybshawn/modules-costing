<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Models\ProductionRun;
use Illuminate\Support\Carbon;

/**
 * Default Run Name for a new production run -- {PREFIX}-{YYMMDD}-{SEQ},
 * a standard small-batch food lot-code shape (facility/brand prefix, the
 * production date, and a same-day sequence so multiple runs on one date
 * still get distinct, traceable codes). The prefix comes from
 * GetBatchCodePrefix; if none has been configured yet, the code is just
 * {YYMMDD}-{SEQ} rather than blocking run creation on it.
 *
 * This only ever supplies the *default* -- ProductionPlannerController::
 * store() uses it solely when the user left Run Name blank, and it stays
 * a perfectly ordinary, freely-editable name field afterward.
 */
class GenerateBatchCode
{
    public function handle(Carbon $runDate): string
    {
        $prefix = app(GetBatchCodePrefix::class)->handle();

        // Sequence is 1-based and scoped to the calendar date across every
        // run type -- a prep or R&D session sharing a date with a real
        // production run still needs a code distinct from it.
        $sequence = ProductionRun::whereDate('run_date', $runDate)->count() + 1;

        $datePart = $runDate->format('ymd');
        $sequencePart = str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);

        return $prefix !== null
            ? "{$prefix}-{$datePart}-{$sequencePart}"
            : "{$datePart}-{$sequencePart}";
    }
}
