<?php

declare(strict_types=1);

namespace App\Modules\Stock\Queries;

use App\Http\Requests\Api\V1\Stock\ListStockAdjustmentRequestRequest;
use App\Modules\Stock\Enums\StockAdjustmentRequestStatus;
use App\Modules\Stock\Models\StockAdjustmentRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Demandes de correction de l'organisation active.
 *
 * Sans tri choisi, les demandes **en attente d'abord**, puis les plus
 * récentes : c'est la file de travail du transporteur. Tout ce que la liste
 * affiche est chargé d'avance — client, article, emplacement et dépôt,
 * demandeur, valideur — pour qu'aucune ligne ne déclenche de requête.
 */
final readonly class StockAdjustmentRequestListQuery
{
    /** @var list<string> */
    private const array SORTABLE = ['created_at', 'reviewed_at', 'status', 'requested_quantity'];

    /** @var list<string> */
    public const array RELATIONS = [
        'customer:id,code,name',
        'stockItem:id,article_code,description',
        'stockLocation:id,depot_id,zone_code,aisle,rack,level,location_code',
        'stockLocation.depot:id,name',
        'requester:id,first_name,last_name',
        'reviewer:id,first_name,last_name',
    ];

    /**
     * @param  array<string, string>  $scoped
     */
    public function paginate(ListStockAdjustmentRequestRequest $request, string $organizationId, array $scoped = []): LengthAwarePaginator
    {
        $query = StockAdjustmentRequest::inOrganization($organizationId)->with(self::RELATIONS);

        foreach ($scoped as $column => $value) {
            $query->where($column, $value);
        }

        foreach (['customerId' => 'customer_id', 'stockItemId' => 'stock_item_id', 'stockLocationId' => 'stock_location_id'] as $input => $column) {
            if ($request->filled($input) && ! array_key_exists($column, $scoped)) {
                $query->where($column, $request->validated($input));
            }
        }

        if ($request->filled('status')) {
            $query->whereIn('status', (array) $request->validated('status'));
        }

        if ($request->filled('createdFrom')) {
            $query->where('created_at', '>=', $request->date('createdFrom')?->startOfDay());
        }

        if ($request->filled('createdTo')) {
            $query->where('created_at', '<=', $request->date('createdTo')?->endOfDay());
        }

        if ($request->filled('search')) {
            $this->search($query, (string) $request->validated('search'));
        }

        return $this->sort($query, $request)->paginate($request->getPerPage());
    }

    /** @param  Builder<StockAdjustmentRequest>  $query */
    private function search(Builder $query, string $term): void
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(static fn (Builder $where) => $where
            ->where('reason', 'like', $like)
            ->orWhereHas('stockItem', static fn (Builder $item) => $item->where('article_code', 'like', $like)));
    }

    /**
     * @param  Builder<StockAdjustmentRequest>  $query
     * @return Builder<StockAdjustmentRequest>
     */
    private function sort(Builder $query, ListStockAdjustmentRequestRequest $request): Builder
    {
        if (! $request->filled('sort')) {
            return $query
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [StockAdjustmentRequestStatus::PENDING->value])
                ->orderByDesc('created_at')
                ->orderByDesc('id');
        }

        $sort = $request->getSort('created_at', self::SORTABLE);

        return $query->orderBy($sort, $request->validated('direction') ?? 'desc')->orderByDesc('id');
    }
}
