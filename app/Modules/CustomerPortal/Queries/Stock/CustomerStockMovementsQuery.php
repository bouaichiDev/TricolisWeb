<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Queries\Stock;

use App\Modules\CustomerPortal\Services\Stock\CustomerStockScope;
use App\Modules\Stock\Models\StockMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les mouvements des articles du client (§16), du plus récent au plus ancien.
 *
 * Mêmes filtres que la liste interne, bornés au périmètre du client. Le type
 * de mouvement est rendu tel que l'interne l'a enregistré : le portail
 * n'invente ni `IN` ni `OUT`.
 */
final readonly class CustomerStockMovementsQuery
{
    public const array RELATIONS = [
        'stockItem:id,article_code,description',
        'sourceLocation:id,depot_id,zone_code,aisle,rack,level,location_code',
        'sourceLocation.depot:id,name',
        'destinationLocation:id,depot_id,zone_code,aisle,rack,level,location_code',
        'destinationLocation.depot:id,name',
    ];

    public function __construct(private CustomerStockScope $scope) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = $this->scope->movements()->with(self::RELATIONS);

        foreach ([
            'stockItemId' => 'stock_item_id', 'sourceLocationId' => 'source_location_id',
            'destinationLocationId' => 'destination_location_id', 'movementType' => 'movement_type',
            'sourceEntityType' => 'source_entity_type', 'sourceEntityId' => 'source_entity_id',
        ] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }

        if (! empty($filters['createdFrom'])) {
            $query->whereDate('created_at', '>=', $filters['createdFrom']);
        }

        if (! empty($filters['createdTo'])) {
            $query->whereDate('created_at', '<=', $filters['createdTo']);
        }

        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy('created_at', $direction)->orderBy('id', $direction)->paginate((int) ($filters['perPage'] ?? 25));
    }

    /**
     * @return Builder<StockMovement>
     */
    public function one(): Builder
    {
        return $this->scope->movements()->with(self::RELATIONS);
    }
}
