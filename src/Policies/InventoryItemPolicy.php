<?php

namespace Cultpantry\Costing\Policies;

use App\Models\User;
use Cultpantry\Costing\Models\InventoryItem;

class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->isAdmin();
    }

    /**
     * Class-level ability (not tied to one InventoryItem instance) for the
     * bulk "stock received / inventory recount" modal.
     */
    public function bulkUpdate(User $user): bool
    {
        return $user->isAdmin();
    }
}
