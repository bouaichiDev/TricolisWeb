<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Services\StatusSources;
use App\Shared\Database\MorphMap;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Confronte les statuts réellement stockés au référentiel.
 *
 * Chaque colonne `status` du domaine porte un code libre. Cette commande
 * parcourt toutes les entités qui en ont une et signale les valeurs qu'aucune
 * ligne de `statuses` ne décrit — pour la bonne source.
 *
 * **Rien n'est supprimé ni corrigé.** Une valeur orpheline est une donnée
 * réelle, produite par un import ou une phase antérieure ; l'effacer perdrait
 * l'information. La commande dit ce qui manque, l'arbitrage reste humain.
 */
class CheckStatuses extends Command
{
    protected $signature = 'tricolis:check-statuses {--source= : N’examiner qu’une entité}';

    protected $description = 'Vérifie que chaque statut stocké existe au référentiel';

    public function handle(): int
    {
        $only = $this->option('source');
        $sources = $only === null ? StatusSources::all() : [$only];

        $orphans = [];
        $examined = 0;

        foreach ($sources as $source) {
            $table = $this->tableOf($source);

            if ($table === null) {
                $this->warn("Source inconnue : {$source}");

                continue;
            }

            $examined++;
            $known = Status::where('source', $source)->pluck('code')->all();

            $used = DB::table($table)->select('status')->distinct()
                ->whereNotNull('status')->where('status', '!=', '')
                ->pluck('status')->all();

            foreach (array_diff($used, $known) as $code) {
                $orphans[] = [
                    'source' => $source,
                    'table' => $table,
                    'code' => (string) $code,
                    'lignes' => DB::table($table)->where('status', $code)->count(),
                ];
            }
        }

        if ($orphans === []) {
            $this->info("{$examined} entité(s) examinée(s) : aucun statut orphelin.");

            return self::SUCCESS;
        }

        $this->error(count($orphans).' statut(s) sans définition au référentiel :');
        $this->table(['Source', 'Table', 'Code', 'Lignes'], $orphans);
        $this->line('Ajoutez-les au référentiel, ou corrigez les données. Rien n’a été modifié.');

        return self::FAILURE;
    }

    /** Table portée par l'alias de morph map, quand il en désigne un modèle. */
    private function tableOf(string $source): ?string
    {
        $class = MorphMap::class($source);

        if ($class === null || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        /** @var Model $model */
        $model = new $class;

        return $model->getTable();
    }
}
