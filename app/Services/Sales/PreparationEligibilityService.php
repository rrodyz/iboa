<?php

namespace App\Services\Sales;

use App\Models\Client;
use App\Models\Order;
use App\Services\Production\ProductionFinancialEligibilityService;

/**
 * [R4.2 → R4.7] SOURCE UNIQUE de la règle « cette commande peut-elle passer en
 * bon de préparation ? ».
 *
 * Le bon de préparation est le point de contrôle du flux : il autorise le
 * magasinier à charger ET rend la commande visible en production (tôles bac).
 * La règle ne doit donc exister qu'à un seul endroit, sinon l'écran, la caisse
 * et la production divergent — c'est exactement ce qui avait laissé passer
 * BUG-A3-MTO-FIN-001.
 *
 * Le montant exigé n'est PAS recalculé ici : il vient de
 * {@see ProductionFinancialEligibilityService::requiredAmount()}, déjà source
 * unique du contrat financier (comptant = 100 % du TTC, acompte = TTC × taux,
 * crédit = aucun chemin par le paiement). Cette classe n'ajoute que la règle
 * d'autorisation propre au BP :
 *
 *   comptant / acompte → encaissements confirmés ≥ montant exigé ;
 *   crédit             → approbation hiérarchique explicite, même dans le
 *                        plafond (R4.4) et a fortiori en dépassement (R4.5) ;
 *   mode inconnu       → refus (fail-closed, jamais de repli permissif).
 */
class PreparationEligibilityService
{
    /** Statuts de commande à partir desquels un BP a un sens. */
    private const ORDER_OPEN = ['confirme', 'en_preparation'];

    public const APPROVAL_PENDING  = 'pending';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';

    public function __construct(
        private readonly ProductionFinancialEligibilityService $financial,
    ) {
    }

    public function evaluate(Order $order): PreparationEligibility
    {
        $covered = (int) $order->confirmedReceipts();

        if (! in_array($order->status, self::ORDER_OPEN, true)) {
            return new PreparationEligibility(
                allowed: false,
                type: PreparationEligibility::TYPE_UNSUPPORTED,
                requiredAmount: 0,
                coveredAmount: $covered,
                reason: sprintf('Commande au statut « %s » : un bon de préparation ne peut être émis que sur une commande confirmée.', $order->status),
            );
        }

        $client = $order->client;
        if (! $client) {
            return new PreparationEligibility(
                allowed: false,
                type: PreparationEligibility::TYPE_UNSUPPORTED,
                requiredAmount: 0,
                coveredAmount: $covered,
                reason: 'Commande sans client rattaché : le mode de règlement est indéterminable.',
            );
        }

        // Comptant et acompte : le montant exigé est connu et se couvre par
        // encaissement. `requiredAmount()` retourne null pour le crédit et pour
        // toute configuration inexploitable (taux d'acompte absurde) — le refus
        // est alors porté par les branches ci-dessous.
        $required = $this->financial->requiredAmount($order);

        if ($required !== null) {
            $satisfait = $covered >= $required;

            return new PreparationEligibility(
                allowed: $satisfait,
                type: PreparationEligibility::TYPE_PAYMENT,
                requiredAmount: $required,
                coveredAmount: $covered,
                reason: $satisfait
                    ? sprintf('Règlement suffisant : %s FCFA encaissés sur %s exigés.', $this->fcfa($covered), $this->fcfa($required))
                    : sprintf(
                        'Règlement insuffisant : %s FCFA encaissés sur %s exigés. Manque %s FCFA avant le bon de préparation.',
                        $this->fcfa($covered), $this->fcfa($required), $this->fcfa($required - $covered),
                    ),
            );
        }

        if ($client->payment_mode === Client::PAYMENT_CREDIT) {
            $statut = $order->preparation_approval_status;

            if ($statut === self::APPROVAL_APPROVED) {
                return new PreparationEligibility(
                    allowed: true,
                    type: PreparationEligibility::TYPE_APPROVAL,
                    requiredAmount: 0,
                    coveredAmount: $covered,
                    reason: sprintf(
                        'Passage en préparation approuvé%s%s.',
                        $order->preparation_approved_at ? ' le '.$order->preparation_approved_at->format('d/m/Y') : '',
                        $order->preparation_approval_reason ? ' — '.$order->preparation_approval_reason : '',
                    ),
                );
            }

            return new PreparationEligibility(
                allowed: false,
                type: PreparationEligibility::TYPE_APPROVAL,
                requiredAmount: 0,
                coveredAmount: $covered,
                reason: match ($statut) {
                    self::APPROVAL_PENDING  => 'Commande à crédit : approbation hiérarchique demandée, en attente de décision.',
                    self::APPROVAL_REJECTED => 'Commande à crédit : approbation refusée'.($order->preparation_approval_reason ? ' — '.$order->preparation_approval_reason : '').'.',
                    default                 => 'Commande à crédit : le passage en bon de préparation exige une approbation hiérarchique.',
                },
                awaitingApproval: $statut !== self::APPROVAL_REJECTED,
            );
        }

        // Mode acompte sans taux exploitable, mode inconnu, client sans mode :
        // la garde financière a déjà refusé, on ne réinvente pas de chemin.
        return new PreparationEligibility(
            allowed: false,
            type: PreparationEligibility::TYPE_UNSUPPORTED,
            requiredAmount: 0,
            coveredAmount: $covered,
            reason: $this->financial->evaluate($order)->reason,
        );
    }

    /** Raccourci booléen — même règle, sans le détail. */
    public function allows(Order $order): bool
    {
        return $this->evaluate($order)->allowed;
    }

    private function fcfa(int $montant): string
    {
        return number_format($montant, 0, ',', ' ');
    }
}
