<?php

declare(strict_types=1);

namespace App\Modules\Stock\Queries;

use App\Http\Requests\Api\V1\Stock\ListStockItemRequest;
use App\Modules\Stock\Models\StockItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche paginée des articles de stock.
 *
 * Ni soldes, ni mouvements, ni réservations chargés — le §8 l'interdit.
 */
final readonly class StockItemListQuery
{
    /** @var list<string> */
    private const array SORTABLE = ['article_code', 'barcode', 'status'];

    /**
     * @param  array<string, string>  $scoped
     */
    public function paginate(ListStockItemRequest $request, string $organizationId, array $scoped = []): LengthAwarePaginator
    {
        $query = StockItem::inOrganization($organizationId)->with('customer:id,code,name');

        foreach ($scoped as $column => $value) {
            $query->where($column, $value);
        }

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(function ($builder) use ($search): void {
                foreach (['article_code', 'barcode', 'description'] as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        foreach ([
            'customerId' => 'customer_id',
            'catalogItemId' => 'catalog_item_id',
            'articleCode' => 'article_code',
            'barcode' => 'barcode',
            'status' => 'status',
        ] as $input => $column) {
            if ($request->filled($input) && ! array_key_exists($column, $scoped)) {
                $query->where($column, $request->validated($input));
            }
        }

        return $query
            ->orderBy($request->getSort('article_code', self::SORTABLE), $request->getDirection())
            ->paginate($request->getPerPage());
    }
}
