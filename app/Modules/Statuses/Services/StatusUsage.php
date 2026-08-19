<?php

declare(strict_types=1);

namespace App\Modules\Statuses\Services;

use App\Modules\Statuses\Models\Status;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Combien d'enregistrements portent encore un statut.
 *
 * Aucune clé étrangère ne relie les colonnes `status` à ce référentiel — elles
 * portaient déjà des données, appartiennent à des tables différentes, et une
 * même valeur existe pour plusieurs entités. L'intégrité est donc vérifiée ici,
 * au moment où elle compte : avant une suppression.
 *
 * Le compte est fait sur la table de l'entité désignée par `source`, ce qui
 * évite d'énumérer les trente-neuf tables concernées.
 */
final readonly class StatusUsage
{
    public function count(Status $status): int
    {
        $class = MorphMap::class($status->source);

        if ($class === null || ! is_subclass_of($class, Model::class)) {
            return 0;
        }

        /** @var Model $model */
        $model = new $class;

        return DB::table($model->getTable())->where('status', $status->code)->count();
    }

    /**
     * @throws ValidationException si des enregistrements le portent encore
     */
    public function assertUnused(Status $status): void
    {
        $count = $this->count($status);

        if ($count === 0) {
            return;
        }

        throw ValidationException::withMessages([
            'code' => [sprintf(
                'Ce statut est encore porté par %d enregistrement(s) : ils afficheraient un code sans libellé.',
                $count,
            )],
        ]);
    }
}
