<?php

declare(strict_types=1);

namespace App\Modules\Stock\Queries;

use App\Http\Requests\Api\V1\Stock\ListStockBalanceRequest;
use App\Modules\Stock\Models\StockBalance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche paginée des soldes. **Lecture seule** : le §14 interdit un CRUD
 * public sur les soldes.
 */
final readonly class StockBalanceListQuery
{
    /** @var list<string> */
    private const array SORTABLE = ['quantity', 'reserved_quantity', 'available_quantity', 'updated_at'];

    /**
     * @param  array<string, string>  $scoped
     */
    public function paginate(ListStockBalanceRequest $request, string $organizationId, array $scoped = []): LengthAwarePaginator
    {
        $query = StockBalance::inOrganization($organizationId)
            ->with(['stockItem:id,customer_id,article_code,description', 'stockLocation:id,depot_id,location_code']);

        foreach ($scoped as $column => $value) {
            $query->where($column, $value);
        }

        foreach ([
            'stockItemId' => 'stock_item_id',
            'stockLocationId' => 'stock_location_id',
        ] as $input => $column) {
            if ($request->filled($input) && ! array_key_exists($column, $scoped)) {
                $query->where($column, $request->validated($input));
            }
        }

        if ($request->filled('customerId')) {
            $customerId = $request->validated('customerId');
            $query->whereHas('stockItem', fn ($item) => $item->where('customer_id', $customerId));
        }

        if ($request->boolean('availableOnly')) {
            $query->where('available_quantity', '>', 0);
        }

        return $query
            ->orderBy($request->getSort('updated_at', self::SORTABLE), $request->getDirection())
            ->paginate($request->getPerPage());
    }
}
