<?php

namespace Cultpantry\Costing\Policies;

use App\Models\User;
use Cultpantry\Costing\Models\ProductionRun;

class ProductionRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ProductionRun $productionRun): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ProductionRun $productionRun): bool
    {
        return $user->isAdmin();
    }

    public function complete(User $user, ProductionRun $productionRun): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ProductionRun $productionRun): bool
    {
        return $user->isAdmin();
    }
}
