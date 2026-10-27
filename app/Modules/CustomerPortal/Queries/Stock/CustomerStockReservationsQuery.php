<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Queries\Stock;

use App\Modules\CustomerPortal\Services\Stock\CustomerStockScope;
use App\Modules\Stock\Models\StockReservation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les réservations du client (§17) : article **et** ligne de commande du
 * client, avec la commande pour « voir la commande liée ».
 */
final readonly class CustomerStockReservationsQuery
{
    public const array RELATIONS = [
        'stockItem:id,article_code,description',
        'stockLocation:id,depot_id,zone_code,aisle,rack,level,location_code',
        'stockLocation.depot:id,name',
        'orderLine:id,order_id,article_code,name,quantity',
        'orderLine.order:id,order_number,customer_reference',
    ];

    public function __construct(private CustomerStockScope $scope) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = $this->one();

        foreach (['stockItemId' => 'stock_item_id', 'locationId' => 'stock_location_id', 'status' => 'status'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }

        if (! empty($filters['orderId'])) {
            $query->whereHas('orderLine', static fn (Builder $line) => $line->where('order_id', $filters['orderId']));
        }

        if (($filters['state'] ?? null) === 'active') {
            $query->whereNull('released_at');
        } elseif (($filters['state'] ?? null) === 'released') {
            $query->whereNotNull('released_at');
        }

        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy('reserved_at', $direction)->orderBy('id', $direction)->paginate((int) ($filters['perPage'] ?? 25));
    }

    /**
     * @return Builder<StockReservation>
     */
    public function one(): Builder
    {
        return $this->scope->reservations()->with(self::RELATIONS);
    }
}
