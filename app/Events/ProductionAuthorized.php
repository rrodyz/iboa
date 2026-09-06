<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * [R4.7] La production d'une commande vient d'être autorisée.
 *
 * Émis quand — et seulement quand — le contrôle financier est franchi :
 * bon de préparation émis (comptant réglé, acompte au seuil, crédit approuvé)
 * ou dérogation gérant posée. C'est ce moment, et non la confirmation
 * commerciale, qui ouvre le droit de fabriquer : une commande confirmée mais
 * non couverte ne doit produire aucun ordre de fabrication.
 */
class ProductionAuthorized
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Order $order) {}
}
