<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The "Unspecified" pseudo-source (Ingredient::booted() used to
     * auto-create one per ingredient as a fallback) is gone as a concept --
     * a source is now either a real, named provider/brand or it doesn't
     * exist. Deletes every existing row unconditionally rather than only
     * where quantity_on_hand is 0 (unlike the live destroySource()
     * endpoint's guard) -- this app is pre-production, so there's no real
     * stock at stake here, matching this session's established stance on
     * schema cleanups over defensive data preservation.
     *
     * Any PriceHistoryEntry/InventoryAdjustment rows that referenced one of
     * these are unaffected beyond their live link going null --
     * package_size_id is nullOnDelete on both, and their provider/brand
     * snapshot columns already preserve "Unspecified" in history, same as
     * deleting any other source.
     */
    public function up(): void
    {
        DB::table('costing_ingredient_package_sizes')
            ->where('provider', 'Unspecified')
            ->whereNull('brand')
            ->delete();
    }

    public function down(): void
    {
        // Intentionally a no-op -- these were auto-created fallback rows,
        // not user data; nothing meaningful to restore.
    }
};
