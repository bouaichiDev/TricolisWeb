<?php

declare(strict_types=1);

namespace App\Modules\Stock\Queries;

use App\Http\Requests\Api\V1\Stock\ListStockMovementRequest;
use App\Modules\Stock\Models\StockMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche paginée des mouvements. Ordre par défaut : du plus récent au plus
 * ancien, comme le §19 le pose.
 */
final readonly class StockMovementListQuery
{
    /** @var list<string> */
    private const array SORTABLE = ['created_at', 'quantity', 'movement_type'];

    public function paginate(ListStockMovementRequest $request, string $organizationId): LengthAwarePaginator
    {
        $query = StockMovement::inOrganization($organizationId);

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder
                ->where('movement_type', 'like', "%{$search}%")
                ->orWhere('source_entity_type', 'like', "%{$search}%"));
        }

        foreach ([
            'stockItemId' => 'stock_item_id',
            'sourceLocationId' => 'source_location_id',
            'destinationLocationId' => 'destination_location_id',
            'movementType' => 'movement_type',
            'sourceEntityType' => 'source_entity_type',
            'sourceEntityId' => 'source_entity_id',
            'createdBy' => 'created_by',
        ] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->validated($input));
            }
        }

        if ($request->filled('createdFrom')) {
            $query->where('created_at', '>=', $request->validated('createdFrom'));
        }

        if ($request->filled('createdTo')) {
            $query->where('created_at', '<=', $request->validated('createdTo'));
        }

        $sort = $request->getSort('created_at', self::SORTABLE);
        $direction = $request->validated('direction') ?? ($sort === 'created_at' ? 'desc' : 'asc');

        return $query->orderBy($sort, $direction)->paginate($request->getPerPage());
    }
}
