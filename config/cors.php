<?php

/*
| Origines autorisées (Phase 8 portail client).
|
| `CORS_ALLOWED_ORIGINS` : liste séparée par des virgules — le back-office et le
| portail client de production, chacun sur son domaine. Sans elle, seul le
| back-office historique est autorisé : le portail déployé ailleurs verrait
| chaque appel refusé par le navigateur.
|
| Les origines `localhost` (développement, parcours Playwright) ne sont
| ajoutées **que hors production**.
*/
$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'https://tricolis.bouaichibadr.com')),
)));

$localOrigins = [
    'http://localhost:5173',
    // Le port des parcours Playwright du back-office (5174), puis le portail
    // client en développement (5180) et sous Playwright (5181, 5182).
    'http://localhost:5174',
    'http://localhost:5180',
    'http://localhost:5181',
    'http://localhost:5182',
    // Le portail fournisseur en développement (5190) et sous Playwright (5191).
    'http://localhost:5190',
    'http://localhost:5191',
];

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => env('APP_ENV') === 'production' ? $origins : [...$origins, ...$localOrigins],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,
];
