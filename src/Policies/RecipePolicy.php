<?php

namespace Cultpantry\Costing\Policies;

use App\Models\User;
use Cultpantry\Costing\Models\Recipe;

class RecipePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Recipe $recipe): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Recipe $recipe): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Recipe $recipe): bool
    {
        return $user->isAdmin();
    }

    /**
     * Hard-delete a recipe the Create form autosaved into existence this
     * session: only while it's fresh (under a day old) and no production
     * run uses it yet.
     */
    public function discardDraft(User $user, Recipe $recipe): bool
    {
        return $user->isAdmin()
            && $recipe->created_at?->greaterThan(now()->subDay())
            && ! $recipe->productionRuns()->exists();
    }
}
