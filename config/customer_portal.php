<?php

/*
|--------------------------------------------------------------------------
| Portail client
|--------------------------------------------------------------------------
|
| L'application React du portail est distincte du frontend interne : les
| liens d'activation et de reinitialisation envoyes aux clients doivent
| mener chez elle, pas dans l'administration du transporteur.
|
*/

return [
    'url' => env('CUSTOMER_PORTAL_URL', 'http://localhost:5180'),

    // Duree de validite d'une invitation, en heures.
    'invitation_expires_hours' => (int) env('CUSTOMER_PORTAL_INVITATION_HOURS', 72),

    /*
    | Sécurité (Phase 7). Valeurs techniques par défaut, documentées dans
    | docs/customer-portal/security.md et ajustables par environnement.
    |
    | Un jeton du portail expire au bout de `token_ttl_minutes` : le client
    | se reconnecte. Les plafonds de débit sont par compte et par minute.
    */
    'token_ttl_minutes' => (int) env('CUSTOMER_PORTAL_TOKEN_TTL_MINUTES', 720),

    'rate_limits' => [
        'api_per_minute' => (int) env('CUSTOMER_PORTAL_RATE_LIMIT', 300),
        'logins_per_minute' => (int) env('CUSTOMER_PORTAL_LOGIN_RATE_LIMIT', 30),
        'imports_per_minute' => (int) env('CUSTOMER_PORTAL_IMPORT_RATE_LIMIT', 10),
        'export_retries_per_minute' => (int) env('CUSTOMER_PORTAL_RETRY_RATE_LIMIT', 10),
    ],

    /*
    | Suivi (Phase 3). Réglages techniques seulement : ce qui est montré au
    | client final se règle par organisation (`settings.publicTracking`).
    */
    'tracking' => [
        // Intervalle de rafraîchissement proposé à l'écran tant que la commande est active.
        'refresh_seconds' => (int) env('CUSTOMER_PORTAL_TRACKING_REFRESH', 45),
        // Durée de mémorisation de la dernière position télématique d'une tournée.
        'position_cache_seconds' => (int) env('CUSTOMER_PORTAL_POSITION_CACHE', 30),
        // Plus longue période qu'une requête du calendrier peut couvrir, et nombre d'événements rendus.
        'calendar_max_days' => (int) env('CUSTOMER_PORTAL_CALENDAR_MAX_DAYS', 100),
        'calendar_max_events' => (int) env('CUSTOMER_PORTAL_CALENDAR_MAX_EVENTS', 500),
    ],

    'public_tracking' => [
        // Page publique du lien de suivi ; par défaut celle du portail.
        'url' => env('CUSTOMER_PORTAL_TRACKING_URL', env('CUSTOMER_PORTAL_URL', 'http://localhost:5180')),
        // Débit par adresse IP sur l'API publique, par minute.
        'rate_limit_per_minute' => (int) env('PUBLIC_TRACKING_RATE_LIMIT', 60),
        // Durée de mise en cache de la réponse publique, en secondes.
        'cache_seconds' => (int) env('PUBLIC_TRACKING_CACHE_SECONDS', 15),
    ],
];
