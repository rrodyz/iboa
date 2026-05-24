<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * [CONCURRENCE-MULTI-USER] Anti-double-soumission de formulaire.
 *
 * Chaque formulaire de création (store) contient un champ caché `_idempotency_key`
 * généré une seule fois au chargement de la page (UUID v4).
 *
 * Si le même token est soumis deux fois en moins de 60 secondes,
 * la deuxième requête est rejetée avec un message d'avertissement.
 *
 * Couvre les cas :
 *  - Double-clic sur "Enregistrer"
 *  - Réseau lent + re-clic utilisateur
 *  - Rechargement de la page après POST
 *  - Ouverture de deux onglets avec le même formulaire
 *
 * Activation sur les routes :
 *   Route::middleware('idempotency')->post('...', ...)
 */
class IdempotencyMiddleware
{
    const TTL_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->input('_idempotency_key');

        if ($key && strlen($key) >= 10) {
            $cacheKey = 'idem:' . auth()->id() . ':' . $key;

            if (Cache::has($cacheKey)) {
                // Double soumission détectée
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Cette action a déjà été effectuée. Actualisez la page.',
                    ], 409);
                }

                return back()->with(
                    'warning',
                    '⚠ Action déjà effectuée. Si le document n\'apparaît pas, actualisez la page (F5).'
                );
            }

            // Marque ce token comme utilisé
            Cache::put($cacheKey, true, now()->addSeconds(self::TTL_SECONDS));
        }

        return $next($request);
    }
}
