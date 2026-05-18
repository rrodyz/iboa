<?php

namespace App\Policies;

use App\Models\InventorySession;
use App\Models\User;

class InventorySessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user, InventorySession $inventorySession): bool
    {
        return $user->can('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.create');
    }

    public function validate(User $user, InventorySession $inventorySession): bool
    {
        return $user->can('inventory.validate');
    }
}
