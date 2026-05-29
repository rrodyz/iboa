<?php

use App\Models\Company;

if (! function_exists('currentCompany')) {
    /**
     * Retourne la société active pour l'utilisateur courant.
     *
     * Résolue par le middleware SetCurrentCompany sur chaque requête web.
     * Dans les jobs / exports lancés depuis une requête, la société est
     * transmise explicitement en paramètre du job/export.
     *
     * @throws \RuntimeException si le middleware n'a pas été exécuté
     */
    function currentCompany(): Company
    {
        if (app()->has('current_company')) {
            return app('current_company');
        }

        // Fallback pour les contextes hors-requête (artisan, queues…)
        return Company::firstOrFail();
    }
}
