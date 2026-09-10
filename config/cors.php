<?php

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://tricolis.bouaichibadr.com',
        'http://localhost:5173',
        // Le port des parcours Playwright. `playwright.config.ts` lance son
        // serveur web sur 5174 pour ne pas entrer en conflit avec un serveur de
        // developpement deja ouvert ; sans cette origine, le navigateur des
        // scenarios se voit refuser chaque appel et l'ecran affiche « Le
        // serveur est injoignable ».
        'http://localhost:5174',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,
];
