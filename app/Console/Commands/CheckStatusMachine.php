<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Models\StatusTransition;
use App\Modules\Statuses\Services\StatusSources;
use App\Shared\Database\MorphMap;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vérifie la cohérence de la machine à états.
 *
 * Depuis que les transitions vivent en base, une mauvaise saisie peut bloquer
 * des commandes : un statut sans issue les fige, un statut inatteignable ne
 * sert à rien, et un code encore porté mais absent du référentiel s'affiche
 * sans libellé. Cette commande les signale avant que quelqu'un ne les découvre
 * en production.
 *
 * Elle ne corrige rien : ce sont des décisions d'administration.
 */
class CheckStatusMachine extends Command
{
    protected $signature = 'tricolis:check-status-machine
                            {--source= : Limiter à une entité (order, package…)}';

    protected $description = 'Signale les statuts sans issue, inatteignables ou orphelins';

    public function handle(): int
    {
        $sources = $this->option('source')
            ? [(string) $this->option('source')]
            : StatusSources::all();

        $problems = 0;

        foreach ($sources as $source) {
            $problems += $this->checkSource($source);
        }

        if ($problems === 0) {
            $this->info('Aucune incohérence.');

            return self::SUCCESS;
        }

        $this->warn("{$problems} point(s) à examiner.");

        return self::SUCCESS;
    }

    private function checkSource(string $source): int
    {
        $statuses = Status::where('source', $source)->get();

        // L'en-tete est toujours ecrite, meme sans statut : sans elle, les
        // codes orphelins d'une entite s'affichaient sous le titre de la
        // precedente et paraissaient lui appartenir.
        $this->line("<comment>{$source}</comment> — {$statuses->count()} statut(s)");

        if ($statuses->isEmpty()) {
            return $this->reportOrphans($source, $statuses);
        }

        $ids = $statuses->pluck('id');
        $transitions = StatusTransition::whereIn('from_status_id', $ids)
            ->orWhereIn('to_status_id', $ids)
            ->get();

        $problems = 0;
        $this->line("  {$transitions->count()} transition(s)");

        // Sans aucune transition, l'entité n'a pas de cycle de vie : c'est un
        // état normal pour la plupart, pas une anomalie.
        if ($transitions->isEmpty()) {
            $this->line('  aucun cycle de vie défini');

            return $problems + $this->reportOrphans($source, $statuses);
        }

        $from = $transitions->pluck('from_status_id')->unique();
        $to = $transitions->pluck('to_status_id')->unique();

        foreach ($statuses as $status) {
            $isFinal = ! $from->contains($status->id);
            $isUnreachable = ! $to->contains($status->id);

            // Un statut final est légitime — « facturée » n'a pas de suite.
            // Un statut ni atteignable ni sortant, en revanche, est mort.
            if ($isFinal && $isUnreachable) {
                $this->warn("  {$status->code} : ni atteignable ni sortant");
                $problems++;
            }
        }

        return $problems + $this->reportOrphans($source, $statuses);
    }

    /**
     * Codes portés en base mais absents du référentiel.
     *
     * @param  Collection<int, Status>  $statuses
     */
    private function reportOrphans(string $source, $statuses): int
    {
        $class = MorphMap::class($source);

        if ($class === null) {
            return 0;
        }

        $table = (new $class)->getTable();
        $known = $statuses->pluck('code')->all();

        $orphans = DB::table($table)
            ->select('status')
            ->selectRaw('count(*) as total')
            ->whereNotNull('status')
            ->when($known !== [], fn ($query) => $query->whereNotIn('status', $known))
            ->groupBy('status')
            ->get();

        foreach ($orphans as $orphan) {
            $this->warn("  {$orphan->status} : {$orphan->total} enregistrement(s) portent un code absent du référentiel");
        }

        return $orphans->count();
    }
}
