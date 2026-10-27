<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Queries;

use App\Modules\Claims\Models\Claim;
use App\Modules\Customers\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les réclamations d'un client, et d'aucun autre (§7).
 *
 * Pas `ClaimListQuery` de l'interne : sa recherche porte aussi sur la cause,
 * la décision et le suivi. Offerte au client, elle lui permettrait de sonder
 * des notes internes qu'il ne lit pas. Ici, la recherche ne voit que le titre
 * et la description — ce que le client a écrit.
 */
final class CustomerClaimListQuery
{
    /** @var array<string, string> tri exposé => colonne */
    public const array SORTS = [
        'createdAt' => 'created_at',
        'closedAt' => 'closed_at',
        'status' => 'status',
        'title' => 'title',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(Customer $customer, array $filters): LengthAwarePaginator
    {
        $query = Claim::query()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->with(['order:id,order_number,customer_reference', 'orderService:id,order_id,service_id,service_number', 'orderService.service:id,name']);

        $this->filter($query, $filters);

        $column = self::SORTS[$filters['sort'] ?? 'createdAt'] ?? 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($column, $direction)->orderBy('id', $direction)->paginate((int) ($filters['perPage'] ?? 25));
    }

    /**
     * @param  Builder<Claim>  $query
     * @param  array<string, mixed>  $filters
     */
    private function filter(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(static fn (Builder $builder) => $builder->where('title', 'like', $search)->orWhere('description', 'like', $search));
        }

        foreach (['status' => 'status', 'claimType' => 'claim_type', 'orderId' => 'order_id', 'orderServiceId' => 'order_service_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->whereIn($column, (array) $filters[$filter]);
            }
        }

        if (($filters['state'] ?? null) === 'open') {
            $query->whereNull('closed_at');
        } elseif (($filters['state'] ?? null) === 'closed') {
            $query->whereNotNull('closed_at');
        }

        foreach (['createdFrom' => ['created_at', '>='], 'createdTo' => ['created_at', '<='], 'closedFrom' => ['closed_at', '>='], 'closedTo' => ['closed_at', '<=']] as $filter => [$column, $operator]) {
            if (! empty($filters[$filter])) {
                $query->whereDate($column, $operator, $filters[$filter]);
            }
        }
    }
}
