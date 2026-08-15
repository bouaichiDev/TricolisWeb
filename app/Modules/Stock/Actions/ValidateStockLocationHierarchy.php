<?php

declare(strict_types=1);

namespace App\Modules\Stock\Actions;

use App\Modules\Stock\Models\StockLocation;
use Illuminate\Validation\ValidationException;

/**
 * Interdit les quatre façons de casser la hiérarchie des emplacements (§10).
 *
 * 1. parent égal à soi-même ;
 * 2. parent descendant — le cycle indirect, le plus facile à créer sans le
 *    vouloir en réorganisant un entrepôt ;
 * 3. déplacement créant une boucle ;
 * 4. parent d'un autre dépôt.
 *
 * **Aucune profondeur maximale n'est fixée** : le §10 l'interdit. La remontée
 * est néanmoins bornée par le nombre d'emplacements du dépôt, et le garde-fou
 * `$visited` la termine même si la base contenait déjà un cycle.
 */
final readonly class ValidateStockLocationHierarchy
{
    /**
     * @param  StockLocation|null  $location  l'emplacement modifié, `null` à la création
     */
    public function execute(?StockLocation $location, string $parentId, string $depotId): StockLocation
    {
        if ($location !== null && $location->id === $parentId) {
            $this->fail('Un emplacement ne peut pas être son propre parent.');
        }

        $parent = StockLocation::whereKey($parentId)->first();

        if ($parent === null) {
            $this->fail('Cet emplacement parent est introuvable.');
        }

        if ($parent->depot_id !== $depotId) {
            $this->fail('L’emplacement parent doit appartenir au même dépôt.');
        }

        if ($location !== null) {
            $this->assertParentIsNotADescendant($location, $parent);
        }

        return $parent;
    }

    /**
     * Remonte la chaîne des parents : si l'emplacement modifié y apparaît, le
     * rattachement fermerait une boucle.
     */
    private function assertParentIsNotADescendant(StockLocation $location, StockLocation $parent): void
    {
        $visited = [];
        $current = $parent;

        while ($current !== null) {
            if ($current->id === $location->id) {
                $this->fail('Cet emplacement parent est un descendant : le rattachement créerait une boucle.');
            }

            // Garde-fou : une boucle deja presente en base ne doit pas faire
            // tourner cette remontee indefiniment.
            if (isset($visited[$current->id])) {
                break;
            }

            $visited[$current->id] = true;
            $current = $current->parent_location_id === null
                ? null
                : StockLocation::whereKey($current->parent_location_id)->first();
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['parentLocationId' => [$message]]);
    }
}
