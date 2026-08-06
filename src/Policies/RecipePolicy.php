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
}
