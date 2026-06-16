<?php

namespace App\Modules\Production\Services;

use App\Models\DeliveryNote;

/**
 * [VENTE ↔ PRODUCTION] Garde-fou de livraison pour les commandes fabriquées.
 *
 * Pour une commande liée à des ordres de fabrication (make-to-order), bloque la
 * validation du bon de livraison si :
 *   - un OF a un contrôle qualité NON CONFORME (dernier contrôle),
 *   - la quantité produite est inférieure à la quantité à livrer.
 *
 * Sans effet sur les commandes hors production (vente sur stock classique) :
 * la disponibilité stock reste contrôlée par StockService lors de la sortie.
 */
class ProductionDeliveryGuard
{
    public function assertDeliverable(DeliveryNote $dn): void
    {
        $order = $dn->order;
        if (! $order) {
            return;
        }

        $ofs = $order->productionOrders()->with(['qualityControls', 'outputs'])->get();
        if ($ofs->isEmpty()) {
            return; // commande non liée à la production
        }

        // 1. Contrôle qualité non conforme → blocage
        foreach ($ofs as $of) {
            $qc = $of->qualityControls->sortByDesc('id')->first();
            if ($qc && $qc->status === 'non_conforme') {
                throw new \RuntimeException(
                    "Livraison bloquée : contrôle qualité non conforme sur l'OF {$of->number}."
                );
            }
        }

        // 2. Quantité produite suffisante
        $produced   = (float) $ofs->sum(fn ($of) => (float) $of->outputs->sum('quantity'));
        $delivering = (float) $dn->loadMissing('items')->items->sum('quantity');

        if ($produced + 0.001 < $delivering) {
            throw new \RuntimeException(
                'Livraison bloquée : quantité produite (' . rtrim(rtrim(number_format($produced, 2, '.', ''), '0'), '.')
                . ') insuffisante pour la quantité à livrer (' . rtrim(rtrim(number_format($delivering, 2, '.', ''), '0'), '.') . ').'
            );
        }
    }
}
