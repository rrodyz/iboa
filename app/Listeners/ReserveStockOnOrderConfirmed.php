<?php

namespace App\Listeners;

use App\Events\OrderConfirmed;
use App\Services\OrderService;

/**
 * [INC-3] Réservation de stock automatique à la confirmation d'une commande.
 *
 * Ce listener écoute l'événement OrderConfirmed et appelle OrderService::reserveStock()
 * pour incrémenter reserved_quantity sur chaque ligne de stock concernée.
 *
 * Pourquoi un listener plutôt qu'un appel direct dans OrderService::confirm() ?
 * Parce que OrderConfirmed peut être dispatché depuis d'autres code paths
 * (QuoteService, API, imports…) — centraliser ici garantit que la réservation
 * se fait toujours, quelle que soit la source de l'événement.
 *
 * Ce listener est SYNCHRONE : il s'exécute dans la même transaction DB que
 * le code qui dispatche l'événement, garantissant l'atomicité
 * (si la réservation échoue, l'ordre n'est pas confirmé).
 */
class ReserveStockOnOrderConfirmed
{
    public function __construct(private readonly OrderService $orderService) {}

    public function handle(OrderConfirmed $event): void
    {
        $this->orderService->reserveStock($event->order);
    }
}
