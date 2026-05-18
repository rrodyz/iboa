<?php

namespace App\Policies;

use App\Models\DeliveryNote;
use App\Models\User;

class DeliveryNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('deliveries.view');
    }

    public function view(User $user, DeliveryNote $deliveryNote): bool
    {
        return $user->can('deliveries.view');
    }

    public function create(User $user): bool
    {
        return $user->can('deliveries.create');
    }

    public function update(User $user, DeliveryNote $deliveryNote): bool
    {
        return $user->can('deliveries.edit');
    }

    public function validate(User $user, DeliveryNote $deliveryNote): bool
    {
        return $user->can('deliveries.validate');
    }
}
