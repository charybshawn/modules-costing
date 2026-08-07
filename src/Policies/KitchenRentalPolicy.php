<?php

namespace Cultpantry\Costing\Policies;

use App\Models\User;
use Cultpantry\Costing\Models\KitchenRental;

class KitchenRentalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, KitchenRental $kitchenRental): bool
    {
        return $user->isAdmin();
    }

    /**
     * Class-level ability for the CSV import form, which doesn't act on
     * one existing KitchenRental instance.
     */
    public function import(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, KitchenRental $kitchenRental): bool
    {
        return $user->isAdmin();
    }
}
