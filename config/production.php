<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Comptabilisation automatique de la production (SYSCOHADA)
    |--------------------------------------------------------------------------
    |
    | Désactivée par défaut. Lorsqu'elle est activée, la clôture d'un OF
    | (statut « terminé ») génère automatiquement :
    |   - la sortie de stock matières premières  (DR 6032 / CR 321)
    |   - la production stockée des produits finis (DR 361 / CR 736)
    |
    | Pour activer : PRODUCTION_ACCOUNTING_ENABLED=true dans .env
    |
    */
    'accounting' => [
        'enabled' => env('PRODUCTION_ACCOUNTING_ENABLED', false),
    ],

];
