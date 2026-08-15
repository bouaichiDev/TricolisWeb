<?php

use App\Modules\Communications\Console\ProcessScheduledCommunications;
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
