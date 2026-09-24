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

    /**
     * Hard-delete a price entry the Log a Price form autosaved into
     * existence this session: only while it's fresh (under a day old).
     */
    public function discardDraft(User $user, PriceHistoryEntry $priceHistoryEntry): bool
    {
        return $user->isAdmin()
            && $priceHistoryEntry->created_at?->greaterThan(now()->subDay());
    }
}
