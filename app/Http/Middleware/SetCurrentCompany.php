<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Résout la société active pour chaque requête web authentifiée.
 *
 * Priorité :
 *   1. session('active_company_id')  — si l'utilisateur a basculé manuellement
 *   2. auth()->user()->company_id    — société d'appartenance
 *   3. Company::firstOrFail()  — fallback ultime (mono-tenant)
 *
 * La société résolue est injectée dans le conteneur sous la clé 'current_company'
 * et accessible via le helper currentCompany() dans tout le code métier.
 */
class SetCurrentCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();

            $company = null;

            // 1. Session override (super-admin uniquement)
            $sessionId = session('active_company_id');
            if ($sessionId) {
                $company = Company::find((int) $sessionId);
            }

            // 2. Société de l'utilisateur
            if (! $company && $user->company_id) {
                $company = $user->company ?? Company::find($user->company_id);
            }

            // 3. Fallback mono-tenant (NE PAS appeler currentCompany() ici — c'est ce middleware qui le résout)
            if (! $company) {
                $company = Company::first();
            }

            if ($company) {
                app()->instance('current_company', $company);
                // Partager avec toutes les vues Blade
                view()->share('currentCompany', $company);
            }
        }

        return $next($request);
    }
}
