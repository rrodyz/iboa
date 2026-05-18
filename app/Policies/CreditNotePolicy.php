<?php

namespace App\Policies;

use App\Models\CreditNote;
use App\Models\User;

class CreditNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('credit_notes.view');
    }

    public function view(User $user, CreditNote $creditNote): bool
    {
        return $user->can('credit_notes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('credit_notes.create');
    }

    public function update(User $user, CreditNote $creditNote): bool
    {
        return $user->can('credit_notes.edit');
    }

    public function delete(User $user, CreditNote $creditNote): bool
    {
        return $user->can('credit_notes.view');
    }

    public function validate(User $user, CreditNote $creditNote): bool
    {
        return $user->can('credit_notes.create');
    }
}
