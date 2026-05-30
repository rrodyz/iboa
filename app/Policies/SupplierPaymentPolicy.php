<?php

namespace App\Policies;

use App\Models\SupplierPayment;
use App\Models\User;

/**
 * [AUDIT-POL] Policy d'autorisation pour les paiements fournisseurs.
 *
 * Permissions utilisées (module achats/trésorerie) :
 *   payments.view   — consulter les paiements fournisseurs
 *   payments.create — enregistrer un nouveau règlement fournisseur
 *   payments.edit   — modifier un paiement non encore comptabilisé
 */
class SupplierPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view');
    }

    public function view(User $user, SupplierPayment $supplierPayment): bool
    {
        return $user->can('payments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('payments.create');
    }

    public function update(User $user, SupplierPayment $supplierPayment): bool
    {
        return $user->can('payments.edit');
    }

    public function delete(User $user, SupplierPayment $supplierPayment): bool
    {
        // Suppression réservée aux paiements non comptabilisés (édition possible)
        return $user->can('payments.edit');
    }
}
