<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * [R4.5] Le plafond d'encours du client est dépassé par la commande soumise.
 *
 * Exception dédiée — et non un RuntimeException nu — parce que l'appelant doit
 * pouvoir distinguer CE refus (rattrapable par une approbation exceptionnelle)
 * des autres erreurs du contrôle de crédit (contrôle hors transaction, client
 * introuvable), qui ne le sont pas. L'exposition figée au moment du refus est
 * transportée par l'exception : c'est le contexte que le responsable lira pour
 * décider, et il ne doit pas être recalculé plus tard.
 */
class CreditLimitExceededException extends RuntimeException
{
    /**
     * @param  array{limited:bool,limit:int,outstanding:int,open_orders:int,new_order:int,deposits:int,projected:int,available:int}  $exposure
     */
    public function __construct(string $message, public readonly array $exposure)
    {
        parent::__construct($message);
    }

    public function overrunAmount(): int
    {
        return max(0, (int) ($this->exposure['projected'] ?? 0) - (int) ($this->exposure['limit'] ?? 0));
    }
}
