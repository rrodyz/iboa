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

if (! function_exists('pdf_image_data')) {
    /**
     * Encode une image du disque public en data-URI base64 pour l'embarquer
     * dans un PDF DomPDF. Ne lève JAMAIS d'exception : retourne null si le
     * fichier est absent, illisible (ACL serveur), vide ou non décodable —
     * la génération du PDF n'est donc jamais bloquée par un logo/cachet.
     *
     * @param  string|null  $relativePath  chemin relatif sous storage/app/public
     */
    function pdf_image_data(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        $path = storage_path('app/public/' . ltrim($relativePath, '/\\'));
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png'        => 'image/png',
            'svg'        => 'image/svg+xml',
            'gif'        => 'image/gif',
            'webp'       => 'image/webp',
            default      => 'image/jpeg',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }
}
