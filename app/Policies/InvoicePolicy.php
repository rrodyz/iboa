<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.edit');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.delete');
    }

    public function validate(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.validate');
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.send');
    }
}
