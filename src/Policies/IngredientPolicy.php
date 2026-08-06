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
}
