<?php

namespace App\Services;

use App\Models\TreasuryApprovalThreshold;
use App\Models\User;

/**
 * [TRESO] Détermination du niveau d'approbation requis pour un décaissement,
 * selon le montant et les seuils configurés (treasury_approval_thresholds).
 */
class TreasuryApprovalService
{
    /**
     * Retourne la règle (seuil) applicable au montant, ou null si aucune
     * approbation n'est requise (montant sous le plus petit seuil / aucun seuil).
     */
    public function findRequiredRule(int $companyId, int $amount): ?TreasuryApprovalThreshold
    {
        return TreasuryApprovalThreshold::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('min_amount', '<=', $amount)
            ->where(fn ($q) => $q->whereNull('max_amount')->orWhere('max_amount', '>=', $amount))
            ->orderByDesc('min_amount')
            ->first();
    }

    /**
     * Hiérarchie d'autorité trésorerie (rang croissant). Une autorité supérieure
     * peut valider tout montant d'un seuil exigeant une autorité égale ou inférieure.
     */
    private const ROLE_RANK = [
        'comptable'   => 1,
        'directeur'   => 2,
        'super_admin' => 3,
    ];

    /**
     * L'utilisateur peut-il approuver un décaissement soumis à cette règle ?
     * Le rôle requis du seuil fait office de niveau MINIMUM (hiérarchie respectée).
     * Un droit treasury.validate de base reste nécessaire.
     */
    public function userCanApprove(User $user, ?TreasuryApprovalThreshold $rule): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }
        // Droit de base pour agir en trésorerie.
        if (! $user->hasPermissionTo('treasury.validate')) {
            return false;
        }
        // Aucun seuil → le droit de base suffit.
        if (! $rule || ! $rule->required_role) {
            return true;
        }
        // Hiérarchie : rang utilisateur ≥ rang requis par le seuil.
        $needed  = self::ROLE_RANK[$rule->required_role] ?? 1;
        $userMax = 0;
        foreach ($user->getRoleNames() as $r) {
            $userMax = max($userMax, self::ROLE_RANK[$r] ?? 0);
        }
        return $userMax >= $needed;
    }
}
