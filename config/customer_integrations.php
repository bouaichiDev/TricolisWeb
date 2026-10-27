<?php

declare(strict_types=1);

/*
 * Réglages techniques du moteur d'intégrations client (portail, Phase 6).
 *
 * Aucune valeur métier ici : des plafonds de sécurité et de robustesse. Ce qui
 * distingue un client d'un autre vit dans ses configurations
 * (`CustomerImportConfiguration`, `CustomerExportConfiguration`), jamais dans
 * ce fichier.
 */
return [

    'files' => [
        // Au-delà, un fichier est refusé avant toute lecture.
        'max_bytes' => (int) env('CUSTOMER_INTEGRATIONS_MAX_FILE_KB', 5120) * 1024,
        // Nombre maximal d'enregistrements lus dans un fichier ou une réponse.
        'max_rows' => (int) env('CUSTOMER_INTEGRATIONS_MAX_ROWS', 5000),
        // Profondeur maximale d'un document XML : un XML légitime de commande
        // ne s'imbrique pas sur des dizaines de niveaux.
        'xml_max_depth' => 32,
    ],

    /*
     * Store de cache des jetons de prévisualisation et des verrous
     * d'idempotence. Il doit être partagé entre requêtes et processus : jamais
     * `array`, même quand le cache par défaut de l'environnement l'est.
     */
    'cache_store' => env('CUSTOMER_INTEGRATIONS_CACHE_STORE', 'database'),

    'uploads' => [
        // Disque privé : jamais `public`, le fichier n'a pas d'URL.
        'disk' => 'local',
        'directory' => 'customer-imports',
        // Durée de vie d'une prévisualisation : au-delà, il faut recharger.
        'ttl_minutes' => 30,
        // Au-delà de ce nombre de commandes, l'import part en file.
        'sync_order_limit' => (int) env('CUSTOMER_INTEGRATIONS_SYNC_ORDER_LIMIT', 200),
        // Conservation du résumé d'un import traité en file.
        'result_ttl_minutes' => 1440,
    ],

    'pull' => [
        'timeout_seconds' => 30,
        'connect_timeout_seconds' => 10,
        'max_response_bytes' => (int) env('CUSTOMER_INTEGRATIONS_MAX_RESPONSE_KB', 5120) * 1024,
        'max_pages' => 50,
        'max_files_per_run' => 50,
        'tries' => 3,
        // Secondes entre deux tentatives d'une erreur passagère.
        'backoff' => [60, 300],
        // Un pull ne tourne jamais deux fois en même temps pour une configuration.
        'lock_seconds' => 3600,
    ],

    'api' => [
        // Appels par minute et par clé sur `/api/v1/client/*`. 60 est le
        // défaut d'API de Laravel : aucun chiffre métier n'a été fourni.
        'rate_limit_per_minute' => (int) env('CUSTOMER_API_RATE_LIMIT_PER_MINUTE', 60),
        // Clés refusées tolérées par minute et par adresse IP avant blocage.
        'failed_attempts_per_minute' => (int) env('CUSTOMER_API_FAILED_ATTEMPTS_PER_MINUTE', 10),
    ],

    'network' => [
        // Hôtes internes explicitement autorisés (liste séparée par des
        // virgules). Vide par défaut : toute adresse privée est refusée.
        'allowed_private_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('CUSTOMER_INTEGRATIONS_ALLOWED_PRIVATE_HOSTS', '')),
        ))),
    ],
];
