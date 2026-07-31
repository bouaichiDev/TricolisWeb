<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Mot de passe des comptes de développement
    |--------------------------------------------------------------------------
    |
    | Utilisé uniquement par les seeders de développement, qui ne s'exécutent
    | qu'en environnement local ou de test. Aucun mot de passe de production
    | n'est stocké dans le dépôt.
    |
    */

    'development_password' => env('DEV_ADMIN_PASSWORD', 'password'),

    /*
    |--------------------------------------------------------------------------
    | Rétention des documents supprimés
    |--------------------------------------------------------------------------
    |
    | Nombre de jours pendant lesquels un document supprimé (suppression
    | logique) et son fichier physique sont conservés avant purge définitive
    | par la commande `documents:purge`.
    |
    */

    'document_retention_days' => (int) env('DOCUMENT_RETENTION_DAYS', 30),

];
