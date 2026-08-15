<?php

declare(strict_types=1);

namespace App\Modules\Tours\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Réattribue les séquences d'un ensemble d'enfants, sans trou ni doublon.
 *
 * Les trois tables séquencées de la phase portent un index unique
 * `(parent, sequence)`. Écrire les nouvelles valeurs une à une le violerait dès
 * que deux éléments s'échangent : la valeur cible d'un élément est encore
 * occupée par un autre. Les séquences sont donc d'abord décalées hors de portée,
 * puis réécrites — deux passes, dans la même transaction.
 *
 * L'appelant doit fournir **tous** les enfants du parent : une liste partielle
 * laisserait des lignes dans le décalage temporaire.
 */
final readonly class SequenceReorderer
{
    /**
     * Décalage temporaire. `sequence` est UNSIGNED : passer par des valeurs
     * négatives est impossible, on décale donc vers le haut.
     */
    private const int OFFSET = 1_000_000;

    /**
     * @param  class-string<Model>  $model
     * @param  list<string>  $orderedIds  identifiants dans leur ordre cible
     */
    public function apply(
        string $model,
        string $parentColumn,
        string $parentId,
        string $sequenceColumn,
        array $orderedIds,
        string $field = 'ids',
    ): void {
        $existing = $model::query()->where($parentColumn, $parentId)->pluck('id')->all();

        $this->assertCoversEveryChild($existing, $orderedIds, $field);

        DB::transaction(function () use ($model, $parentColumn, $parentId, $sequenceColumn, $orderedIds): void {
            $model::query()
                ->where($parentColumn, $parentId)
                ->update([$sequenceColumn => DB::raw($sequenceColumn.' + '.self::OFFSET)]);

            foreach ($orderedIds as $index => $id) {
                $model::query()->whereKey($id)->update([$sequenceColumn => $index + 1]);
            }
        });
    }

    /**
     * @param  list<string>  $existing
     * @param  list<string>  $orderedIds
     */
    private function assertCoversEveryChild(array $existing, array $orderedIds, string $field): void
    {
        if (count($orderedIds) !== count(array_unique($orderedIds))) {
            throw ValidationException::withMessages([$field => ['Un même élément est listé plusieurs fois.']]);
        }

        sort($existing);
        $submitted = $orderedIds;
        sort($submitted);

        if ($existing !== $submitted) {
            throw ValidationException::withMessages([
                $field => ['La réorganisation doit lister exactement tous les éléments, une fois chacun.'],
            ]);
        }
    }
}
