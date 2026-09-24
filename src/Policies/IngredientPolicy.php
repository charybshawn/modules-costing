<?php

namespace Cultpantry\Costing\Policies;

use App\Models\User;
use Cultpantry\Costing\Models\Ingredient;

class IngredientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Ingredient $ingredient): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Ingredient $ingredient): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Ingredient $ingredient): bool
    {
        return $user->isAdmin();
    }

    /**
     * Hard-delete an ingredient the Create form autosaved into existence
     * this session: only while it's fresh (under a day old) and nothing
     * outside it depends on it yet (no recipe uses it, no stock was
     * counted). Its own sources and prices go with it.
     */
    public function discardDraft(User $user, Ingredient $ingredient): bool
    {
        return $user->isAdmin()
            && $ingredient->created_at?->greaterThan(now()->subDay())
            && ! $ingredient->recipes()->exists()
            && ! $ingredient->inventoryAdjustments()->exists();
    }
}
