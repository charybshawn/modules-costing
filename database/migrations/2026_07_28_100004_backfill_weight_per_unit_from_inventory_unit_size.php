<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * CalculateIngredientCosting is dropping InventoryItem.unit_size from
     * its purchase_size fallback chain (Ingredient.weight_per_unit already
     * exists for exactly this job, and having both was the source of the
     * "which one wins" confusion). Any ingredient currently relying on
     * unit_size as its only purchase-size source needs that number
     * preserved in weight_per_unit first, or its shopping-list sizing
     * regresses. Only fills nulls -- never overwrites an
     * already-set weight_per_unit.
     */
    public function up(): void
    {
        $rows = DB::table('costing_inventory')
            ->join('costing_ingredients', 'costing_ingredients.id', '=', 'costing_inventory.ingredient_id')
            ->whereNull('costing_ingredients.weight_per_unit')
            ->where('costing_inventory.unit_size', '>', 0)
            ->select('costing_ingredients.id as ingredient_id', 'costing_inventory.unit_size')
            ->get();

        foreach ($rows as $row) {
            DB::table('costing_ingredients')->where('id', $row->ingredient_id)->update(['weight_per_unit' => $row->unit_size]);
        }
    }

    public function down(): void
    {
        // Deliberately irreversible -- there's no way to tell which
        // weight_per_unit values were user-entered vs backfilled by this
        // migration, so reverting would risk clearing real data.
    }
};
