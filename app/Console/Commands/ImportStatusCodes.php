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
 * Fait entrer dans le référentiel les codes que les données portent déjà.
 *
 * `StatusSeeder` ne sème que les entités gouvernées par une énumération PHP :
 * ce sont les seules dont on connaisse la liste complète. Les autres — lignes
 * de commande, colis, adresses… — portent des chaînes libres, et leur cycle de
 * vie reste à décrire.
 *
 * En attendant, les codes réellement stockés existent : les importer donne à
 * l'administrateur un point de départ réel plutôt qu'un écran vide, sans rien
 * inventer. Le libellé reprend le code ; c'est à lui de le nommer.
 *
 * Rejouable : un code déjà présent n'est pas touché.
 */
class ImportStatusCodes extends Command
{
    protected $signature = 'tricolis:import-status-codes
                            {--source= : Limiter à une entité (order_line, package…)}
                            {--dry-run : Afficher sans écrire}';

    protected $description = 'Ajoute au référentiel les codes de statut présents dans les données';

    public function handle(): int
    {
        $sources = $this->option('source')
            ? [(string) $this->option('source')]
            : StatusSources::all();

        $created = 0;

        foreach ($sources as $source) {
            $created += $this->importSource($source);
        }

        if ($created === 0) {
            $this->info('Aucun code à importer.');

            return self::SUCCESS;
        }

        $this->info("{$created} statut(s) ajouté(s) au référentiel.");

        if ($this->option('dry-run')) {
            $this->warn('Simulation : rien n’a été écrit.');
        }

        return self::SUCCESS;
    }

    private function importSource(string $source): int
    {
        $class = MorphMap::class($source);

        if ($class === null || ! is_subclass_of($class, Model::class)) {
            return 0;
        }

        /** @var Model $model */
        $model = new $class;

        $codes = DB::table($model->getTable())
            ->whereNotNull('status')
            ->distinct()
            ->pluck('status')
            ->filter(static fn ($code): bool => is_string($code) && $code !== '')
            ->all();

        if ($codes === []) {
            return 0;
        }

        $known = Status::where('source', $source)->pluck('code')->all();
        $missing = array_values(array_diff($codes, $known));

        if ($missing === []) {
            return 0;
        }

        $rank = (int) Status::where('source', $source)->max('status');
        $created = 0;

        foreach ($missing as $code) {
            $rank++;
            $this->line("  <comment>{$source}</comment> : {$code}");

            if ($this->option('dry-run')) {
                $created++;

                continue;
            }

            Status::create([
                'source' => $source,
                'code' => $code,
                'status' => $rank,
                // Le libellé reprend le code : le nommer est une décision
                // d'administration, pas une déduction.
                'label' => $code,
                'position' => $rank * 10,
            ]);

            $created++;
        }

        return $created;
    }
}
