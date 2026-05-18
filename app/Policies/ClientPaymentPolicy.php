<?php

namespace App\Policies;

use App\Models\ClientPayment;
use App\Models\User;

class ClientPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view');
    }

    public function view(User $user, ClientPayment $payment): bool
    {
        return $user->can('payments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('payments.create');
    }

    public function update(User $user, ClientPayment $payment): bool
    {
        return $user->can('payments.edit');
    }
}
