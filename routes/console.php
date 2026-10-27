<?php

use App\Modules\Communications\Console\ProcessScheduledCommunications;
use App\Modules\Integrations\Console\PruneCustomerImportUploads;
use App\Modules\Integrations\Console\PullCustomerIntegrations;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Les communications programmées sont relevées chaque minute : c'est la plus
 * fine granularité qu'offre le scheduler, et `scheduledAt` est un datetime.
 *
 * `withoutOverlapping` évite qu'une exécution lente en croise une autre. Le
 * verrou par ligne de la transition suffirait à empêcher un double envoi, mais
 * deux passes concurrentes feraient un travail inutile.
 */
Schedule::command(ProcessScheduledCommunications::class)
    ->everyMinute()
    ->withoutOverlapping();

/*
 * Imports client automatiques (pull REST, relève FTP/SFTP) : une passe par
 * heure, pour **toutes** les configurations actives — aucun client n'est nommé
 * ici. La commande ne fait que mettre en file ; chaque passe est un Job unique
 * par configuration, sans chevauchement.
 */
Schedule::command(PullCustomerIntegrations::class)
    ->hourly()
    ->withoutOverlapping();

/*
 * Les fichiers d'import prévisualisés puis abandonnés ne restent pas sur le
 * disque au-delà de leur jeton.
 */
Schedule::command(PruneCustomerImportUploads::class)
    ->hourly()
    ->withoutOverlapping();

/*
 * Phase 7 : les jetons Sanctum échus (portail client, 12 h par défaut) ne
 * servent plus à rien ; ils sont effacés une fois par jour, au-delà de 24 h.
 */
Schedule::command('sanctum:prune-expired --hours=24')
    ->daily()
    ->withoutOverlapping();
