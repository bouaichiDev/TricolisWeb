<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Queries\Stock;

use App\Modules\CustomerPortal\Services\Stock\CustomerStockScope;
use App\Modules\Stock\Models\StockItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les articles en stock du client, avec leurs totaux (§10-§11).
 *
 * Les quantités sont **agrégées en SQL** (`withSum` sur les balances) : une
 * requête pour la page, quel que soit le nombre d'emplacements, et aucune
 * colonne de total sur `StockItem`. Un article sans balance a 0 partout.
 */
final readonly class CustomerStockItemsQuery
{
    /** @var array<string, string> tri exposé => colonne */
    public const array SORTS = [
        'articleCode' => 'article_code',
        'description' => 'description',
        'status' => 'status',
        'quantity' => 'quantity_total',
        'availableQuantity' => 'available_total',
    ];

    public function __construct(private CustomerStockScope $scope) {}

    /**
     * @return Builder<StockItem>
     */
    public function withTotals(): Builder
    {
        return $this->scope->items()
            ->withSum('balances as quantity_total', 'quantity')
            ->withSum('balances as reserved_total', 'reserved_quantity')
            ->withSum('balances as available_total', 'available_quantity');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = $this->withTotals();
        $this->filter($query, $filters);

        $column = self::SORTS[$filters['sort'] ?? 'articleCode'] ?? 'article_code';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($column, $direction)->orderBy('id')->paginate((int) ($filters['perPage'] ?? 25));
    }

    /**
     * @param  Builder<StockItem>  $query
     * @param  array<string, mixed>  $filters
     */
    private function filter(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(static fn (Builder $builder) => $builder
                ->where('article_code', 'like', $search)
                ->orWhere('barcode', 'like', $search)
                ->orWhere('description', 'like', $search));
        }

        foreach (['catalogItemId' => 'catalog_item_id', 'articleCode' => 'article_code', 'barcode' => 'barcode'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }

        if (! empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        }

        // Dépôt ou emplacement : un article y figure s'il y a une balance.
        if (! empty($filters['locationId'])) {
            $query->whereHas('balances', static fn (Builder $balance) => $balance->where('stock_location_id', $filters['locationId']));
        }

        if (! empty($filters['depotId'])) {
            $query->whereHas('balances.stockLocation', static fn (Builder $location) => $location->where('depot_id', $filters['depotId']));
        }
    }
}
