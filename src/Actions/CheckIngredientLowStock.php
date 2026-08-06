<?php

namespace Cultpantry\Costing\Actions;

use App\Models\User;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Notifications\LowStockWarning;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Shared by both trigger points -- completing a production run (auto
 * deduct) and manually editing Inventory -- so "notify whenever an
 * inventory item is low" behaves the same regardless of how it went low.
 */
class CheckIngredientLowStock
{
    public function handle(Ingredient $ingredient, float $oldOnHand, float $newOnHand): void
    {
        $threshold = $ingredient->low_stock_threshold;

        if ($threshold === null) {
            return;
        }

        // Crossing-edge only, matching the existing Product/OrderItem
        // pattern (app/Models/OrderItem.php:69) -- fires once on the
        // transition into low stock, not on every check while already low.
        if ((float) $newOnHand > (float) $threshold || (float) $oldOnHand <= (float) $threshold) {
            return;
        }

        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();

        foreach ($admins as $admin) {
            try {
                Notification::send($admin, new LowStockWarning($ingredient, $oldOnHand, $newOnHand));
            } catch (\Exception $e) {
                Log::error('Failed to send costing low-stock notification', [
                    'admin_id' => $admin->id,
                    'ingredient_id' => $ingredient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
