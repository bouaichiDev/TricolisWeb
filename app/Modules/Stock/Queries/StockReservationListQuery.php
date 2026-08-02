<?php

declare(strict_types=1);

namespace App\Modules\Stock\Queries;

use App\Http\Requests\Api\V1\Stock\ListStockReservationRequest;
use App\Modules\Stock\Models\StockReservation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class StockReservationListQuery
{
    /** @var list<string> */
    private const array SORTABLE = ['reserved_at', 'released_at', 'quantity', 'status'];

    public function paginate(ListStockReservationRequest $request, string $organizationId): LengthAwarePaginator
    {
        $query = StockReservation::inOrganization($organizationId);

        foreach ([
            'stockItemId' => 'stock_item_id',
            'stockLocationId' => 'stock_location_id',
            'orderLineId' => 'order_line_id',
            'status' => 'status',
        ] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->validated($input));
            }
        }

        foreach ([
            'reservedFrom' => ['reserved_at', '>='],
            'reservedTo' => ['reserved_at', '<='],
            'releasedFrom' => ['released_at', '>='],
            'releasedTo' => ['released_at', '<='],
        ] as $input => [$column, $operator]) {
            if ($request->filled($input)) {
                $query->where($column, $operator, $request->validated($input));
            }
        }

        $sort = $request->getSort('reserved_at', self::SORTABLE);
        $direction = $request->validated('direction') ?? ($sort === 'reserved_at' ? 'desc' : 'asc');

        return $query->orderBy($sort, $direction)->paginate($request->getPerPage());
    }
}
