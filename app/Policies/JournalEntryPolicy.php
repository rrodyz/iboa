<?php

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\User;

/**
 * [AUDIT-POL] Policy d'autorisation pour les écritures comptables.
 *
 * Permissions utilisées (module accounting) :
 *   accounting.view     — consulter les écritures
 *   accounting.write    — créer/modifier des écritures en brouillon
 *   accounting.validate — valider (comptabiliser) une écriture
 *   accounting.manage   — supprimer, contre-passer, administration
 */
class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.view');
    }

    public function view(User $user, JournalEntry $journalEntry): bool
    {
        return $user->can('accounting.view');
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.write');
    }

    public function update(User $user, JournalEntry $journalEntry): bool
    {
        // Seules les écritures en brouillon sont modifiables
        return $user->can('accounting.write') && $journalEntry->status === 'brouillon';
    }

    public function delete(User $user, JournalEntry $journalEntry): bool
    {
        // Seules les écritures en brouillon peuvent être supprimées
        return $user->can('accounting.manage') && $journalEntry->status === 'brouillon';
    }

    public function validate(User $user, JournalEntry $journalEntry): bool
    {
        return $user->can('accounting.validate') && $journalEntry->status === 'brouillon';
    }

    public function reverse(User $user, JournalEntry $journalEntry): bool
    {
        // Contre-passer une écriture validée (extourne)
        return $user->can('accounting.manage') && $journalEntry->status !== 'brouillon';
    }
}
