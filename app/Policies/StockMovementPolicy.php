<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stocks.view');
    }

    public function view(User $user, StockMovement $movement): bool
    {
        return $user->can('stocks.view');
    }

    public function create(User $user): bool
    {
        return $user->can('stocks.adjust');
    }

    public function transfer(User $user): bool
    {
        return $user->can('stocks.transfer');
    }
}
