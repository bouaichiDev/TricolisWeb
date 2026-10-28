<?php

/*
|--------------------------------------------------------------------------
| Portail fournisseur
|--------------------------------------------------------------------------
|
| L'application React du portail fournisseur est distincte du back-office et
| du portail client : les liens d'activation et de réinitialisation envoyés
| aux fournisseurs doivent mener chez elle.
|
| Un jeton du portail expire au bout de `token_ttl_minutes` : le fournisseur
| se reconnecte. Les plafonds de débit sont par compte et par minute.
|
*/

return [
    'url' => env('PROVIDER_PORTAL_URL', 'http://localhost:5190'),

    'invitation_expires_hours' => (int) env('PROVIDER_PORTAL_INVITATION_HOURS', 72),

    'token_ttl_minutes' => (int) env('PROVIDER_PORTAL_TOKEN_TTL_MINUTES', 720),

    'rate_limits' => [
        'api_per_minute' => (int) env('PROVIDER_PORTAL_RATE_LIMIT', 300),
        'logins_per_minute' => (int) env('PROVIDER_PORTAL_LOGIN_RATE_LIMIT', 30),
        'writes_per_minute' => (int) env('PROVIDER_PORTAL_WRITE_RATE_LIMIT', 30),
        'executions_per_minute' => (int) env('PROVIDER_PORTAL_EXECUTE_RATE_LIMIT', 120),
    ],
];
