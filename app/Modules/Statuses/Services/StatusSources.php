<?php

declare(strict_types=1);

namespace App\Modules\Statuses\Services;

use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Entités auxquelles un statut peut se rapporter.
 *
 * La liste est **dérivée**, jamais recopiée : elle parcourt la morph map et
 * retient les entités dont la table porte réellement une colonne `status`. Une
 * liste écrite à la main divergerait au premier module ajouté, et laisserait
 * créer des statuts pour une entité qui n'en a pas.
 *
 * Le résultat est mémorisé pour le processus : l'inspection du schéma coûte une
 * requête par table, et cette liste ne change pas en cours d'exécution.
 */
final class StatusSources
{
    /** @var list<string>|null */
    private static ?array $cache = null;

    /**
     * @return list<string> alias de morph map, triés
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $sources = [];

        foreach (MorphMap::registered() as $alias => $class) {
            if (! is_subclass_of($class, Model::class)) {
                continue;
            }

            // Le référentiel lui-même porte une colonne `status`, mais c'est
            // l'identifiant numérique du statut, pas l'état d'un statut :
            // s'y référencer n'aurait aucun sens.
            if ($alias === MorphMap::STATUS) {
                continue;
            }

            /** @var Model $model */
            $model = new $class;

            if (Schema::hasColumn($model->getTable(), 'status')) {
                $sources[] = (string) $alias;
            }
        }

        sort($sources);

        return self::$cache = $sources;
    }

    public static function supports(string $source): bool
    {
        return in_array($source, self::all(), true);
    }

    /** Vide le cache — utile entre deux tests qui migrent le schéma. */
    public static function flush(): void
    {
        self::$cache = null;
    }
}
