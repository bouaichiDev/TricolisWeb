<?php

declare(strict_types=1);

namespace App\Modules\Stock\Queries;

use App\Http\Requests\Api\V1\Stock\ListStockLocationRequest;
use App\Modules\Stock\Models\StockLocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Recherche paginée des emplacements, et construction de l'arbre.
 *
 * L'arbre est **dérivé** de `stock_locations` par un seul `SELECT`, puis
 * assemblé en mémoire : le §10 interdit toute table supplémentaire.
 */
final readonly class StockLocationListQuery
{
    /** @var list<string> */
    private const array SORTABLE = ['zone_code', 'aisle', 'rack', 'level', 'location_code', 'status'];

    public function paginate(ListStockLocationRequest $request, string $organizationId): LengthAwarePaginator
    {
        $query = StockLocation::inOrganization($organizationId)->withCount('children');

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(function ($builder) use ($search): void {
                foreach (['zone_code', 'aisle', 'rack', 'level', 'location_code', 'barcode'] as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        foreach ([
            'depotId' => 'depot_id',
            'parentLocationId' => 'parent_location_id',
            'zoneCode' => 'zone_code',
            'aisle' => 'aisle',
            'rack' => 'rack',
            'level' => 'level',
            'locationCode' => 'location_code',
            'barcode' => 'barcode',
            'status' => 'status',
        ] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->validated($input));
            }
        }

        return $query
            ->orderBy($request->getSort('location_code', self::SORTABLE), $request->getDirection())
            ->paginate($request->getPerPage());
    }

    /**
     * Arbre des emplacements de l'organisation, éventuellement d'un seul dépôt.
     *
     * Un seul `SELECT`, puis un assemblage en mémoire : rattacher chaque
     * emplacement à son parent par un `whereHas` récursif produirait autant de
     * requêtes que de niveaux.
     *
     * @return Collection<int, StockLocation>
     */
    public function tree(string $organizationId, ?string $depotId = null): Collection
    {
        $locations = StockLocation::inOrganization($organizationId)
            ->when($depotId !== null, fn ($query) => $query->where('depot_id', $depotId))
            ->orderBy('location_code')
            ->get();

        $byParent = $locations->groupBy('parent_location_id');

        $locations->each(function (StockLocation $location) use ($byParent): void {
            $location->setRelation('children', $byParent->get($location->id, new Collection));
        });

        return $byParent->get(null, new Collection)->values();
    }
}
