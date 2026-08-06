<?php

namespace Cultpantry\Costing\Policies;

use App\Models\User;
use Cultpantry\Costing\Models\PriceHistoryEntry;

class PriceHistoryEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, PriceHistoryEntry $priceHistoryEntry): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, PriceHistoryEntry $priceHistoryEntry): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, PriceHistoryEntry $priceHistoryEntry): bool
    {
        return $user->isAdmin();
    }
}
