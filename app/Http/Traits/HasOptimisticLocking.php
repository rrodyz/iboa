<?php

namespace App\Http\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * [CONCURRENCE-MULTI-USER] Verrou optimiste basé sur updated_at.
 *
 * Protège contre les écrasements silencieux : si l'utilisateur A ouvre un
 * document, et que l'utilisateur B le modifie en premier, la tentative de
 * sauvegarde de A sera rejetée avec un message explicite.
 *
 * Utilisation dans un Service :
 *
 *   use HasOptimisticLocking;
 *
 *   public function update(Invoice $invoice, array $data): Invoice
 *   {
 *       $this->assertVersion($invoice, $data['_lock_version'] ?? null);
 *       unset($data['_lock_version']);
 *       // ... suite
 *   }
 */
trait HasOptimisticLocking
{
    /**
     * Vérifie que le document n'a pas été modifié depuis l'ouverture du formulaire.
     *
     * @param  Model    $model        Le document à sauvegarder
     * @param  mixed    $clientVersion Le timestamp updated_at envoyé par le formulaire
     * @throws \RuntimeException si une version plus récente existe en base
     */
    protected function assertVersion(Model $model, mixed $clientVersion): void
    {
        // Si aucune version n'est fournie (ancien formulaire), on laisse passer
        if ($clientVersion === null || $clientVersion === '') {
            return;
        }

        $serverTimestamp = $model->updated_at?->timestamp;

        if ($serverTimestamp !== null && (int) $clientVersion !== $serverTimestamp) {
            throw new \RuntimeException(
                '⚠ Ce document a été modifié par un autre utilisateur depuis que vous l\'avez ouvert. '
                . 'Rechargez la page pour voir les dernières modifications, puis recommencez votre saisie.'
            );
        }
    }
}
