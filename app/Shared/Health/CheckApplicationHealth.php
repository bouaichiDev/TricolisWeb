<?php

declare(strict_types=1);

namespace App\Shared\Health;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ce que `/up` vérifie au-delà du démarrage (Phase 8 portail, §33).
 *
 * Branché sur `DiagnosingHealth`, l'événement que la route de santé de
 * Laravel émet : aucune nouvelle route, aucune donnée exposée. Une dépendance
 * en panne lève une exception, `/up` répond 500 — sans détail en production
 * (`APP_DEBUG=false`), le message ne nomme que le composant.
 *
 * Vérifié : la base (une requête triviale), le disque privé où vivent
 * documents, imports et exports (écriture puis suppression d'un témoin), le
 * cache (aller-retour d'une valeur). La file n'est pas sondée : un worker
 * arrêté ne se voit pas depuis une requête — voir le runbook.
 */
final class CheckApplicationHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        $this->check('database', static fn (): bool => DB::select('select 1 as ok') !== []);

        $this->check('storage', static function (): bool {
            $probe = 'health/'.Str::random(16).'.txt';
            $disk = Storage::disk('local');
            $written = $disk->put($probe, 'ok');
            $disk->delete($probe);

            return $written;
        });

        $this->check('cache', static function (): bool {
            $key = 'health:'.Str::random(16);
            Cache::put($key, 'ok', 10);
            $read = Cache::get($key) === 'ok';
            Cache::forget($key);

            return $read;
        });
    }

    /**
     * @param  callable(): bool  $probe
     */
    private function check(string $component, callable $probe): void
    {
        try {
            $healthy = $probe();
        } catch (\Throwable) {
            $healthy = false;
        }

        if (! $healthy) {
            throw new RuntimeException("Health check failed: {$component}.");
        }
    }
}
