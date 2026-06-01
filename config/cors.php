<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration — A3 ERP
    |--------------------------------------------------------------------------
    |
    | Configuré pour l'API REST Sanctum (Bearer token).
    | En production : restreindre allowed_origins à votre domaine réel.
    |
    | allowed_origins en production :
    |   ['https://votre-domaine.com', 'https://www.votre-domaine.com']
    |
    | Pour les clients mobiles (React Native, Flutter) :
    |   Ajouter l'origine de l'app ou mettre '*' uniquement si API publique.
    */

    // Chemins couverts par la politique CORS
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // En développement : localhost permis
    // En production   : remplacer par ['https://votre-domaine.com']
    'allowed_origins' => array_filter(
        explode(',', env('CORS_ALLOWED_ORIGINS', 'http://127.0.0.1,http://localhost,http://localhost:5173'))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    // Durée de cache preflight OPTIONS (secondes)
    'max_age' => 3600,

    // true = envoyer les cookies de session dans les requêtes cross-origin
    // (nécessaire pour Sanctum cookie-based auth depuis un SPA sur un autre port)
    'supports_credentials' => true,

];
