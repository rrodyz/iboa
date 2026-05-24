<?php

namespace App\Http\Traits;

use App\Services\EditLockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * [CONCURRENCE-MULTI-USER] Trait pour les contrôleurs qui gèrent des documents éditables.
 *
 * Utilisation dans un contrôleur :
 *
 *   use ManagesEditLock;
 *
 *   public function edit(Invoice $facture): View|RedirectResponse
 *   {
 *       $lock = $this->acquireLockOr($facture, 'achats.commandes.show', $facture);
 *       if ($lock instanceof RedirectResponse) return $lock;
 *       // ... suite normale
 *   }
 *
 *   public function update(UpdateRequest $request, Invoice $facture): RedirectResponse
 *   {
 *       // ... save
 *       $this->releaseLock($facture);
 *       return redirect()->route('...');
 *   }
 */
trait ManagesEditLock
{
    private function lockService(): EditLockService
    {
        return app(EditLockService::class);
    }

    /**
     * Tente d'acquérir le verrou sur $model.
     * Si un autre utilisateur détient le verrou, redirige vers $redirectRoute avec un message d'erreur.
     *
     * @param  Model        $model         Le document à verrouiller
     * @param  string       $redirectRoute Route nommée si refus (ex: 'achats.commandes.show')
     * @param  mixed        ...$routeParams Paramètres de la route de redirection
     * @return \App\Models\EditLock|RedirectResponse
     */
    protected function acquireLockOr(Model $model, string $redirectRoute, mixed ...$routeParams): mixed
    {
        $lock = $this->lockService()->acquire($model, Auth::user());

        if (!$lock) {
            $existing = $this->lockService()->check($model);
            $who      = $existing?->user?->name ?? 'un autre utilisateur';
            $since    = $existing ? $existing->locked_at->diffForHumans() : '';

            return redirect()
                ->route($redirectRoute, ...$routeParams)
                ->with('warning', "⚠ Ce document est en cours de modification par {$who} ({$since}). Réessayez dans quelques minutes ou demandez-lui de sauvegarder.");
        }

        return $lock;
    }

    /**
     * Libère le verrou de l'utilisateur courant sur $model.
     */
    protected function releaseLock(Model $model): void
    {
        $this->lockService()->release($model, Auth::user());
    }

    /**
     * Injecte les données de verrou dans la vue (pour afficher le bandeau).
     * Retourne un tableau compact(['editLock' => ...]).
     */
    protected function lockDataFor(Model $model): array
    {
        $editLock = $this->lockService()->check($model);
        return compact('editLock');
    }
}
