<?php

namespace App\Services\Sales;

/**
 * [R4.2 → R4.5] Verdict d'éligibilité au bon de préparation.
 *
 * Objet de lecture pur : il dit si le BP peut être émis, ce qu'il manque le cas
 * échéant, et par quel chemin l'autorisation s'obtiendrait (encaissement ou
 * approbation hiérarchique). Il n'écrit rien et ne décide d'aucun effet de bord.
 */
class PreparationEligibility
{
    /** Encaissement suffisant : comptant intégral ou acompte au seuil. */
    public const TYPE_PAYMENT = 'payment';

    /** Ligne de crédit : passage soumis à approbation hiérarchique. */
    public const TYPE_APPROVAL = 'approval';

    /** Mode de règlement non reconnu ou commande non éligible : refus. */
    public const TYPE_UNSUPPORTED = 'unsupported';

    public function __construct(
        public readonly bool $allowed,
        public readonly string $type,
        public readonly int $requiredAmount,
        public readonly int $coveredAmount,
        public readonly string $reason,
        public readonly bool $awaitingApproval = false,
    ) {
    }

    /** Reste à encaisser avant de pouvoir émettre le bon de préparation. */
    public function missingAmount(): int
    {
        return max(0, $this->requiredAmount - $this->coveredAmount);
    }
}
