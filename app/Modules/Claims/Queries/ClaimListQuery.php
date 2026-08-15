<?php

declare(strict_types=1);

namespace App\Modules\Claims\Queries;

use App\Http\Requests\Api\V1\Claims\ListClaimRequest;
use App\Modules\Claims\Models\Claim;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche paginée des réclamations de l'organisation active.
 *
 * `createdFrom` et `createdTo` viennent de `ListRequest` partagé : la
 * convention existe depuis la Phase 1 et n'est pas redéfinie ici.
 */
final readonly class ClaimListQuery
{
    /** @var list<string> */
    private const array SORTABLE = ['created_at', 'closed_at', 'cost', 'status', 'title'];

    /**
     * @param  array<string, string>  $scoped
     */
    public function paginate(ListClaimRequest $request, string $organizationId, array $scoped = []): LengthAwarePaginator
    {
        $query = Claim::inOrganization($organizationId)->with('customer:id,code,name');

        foreach ($scoped as $column => $value) {
            $query->where($column, $value);
        }

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(function ($builder) use ($search): void {
                foreach (['title', 'description', 'cause', 'decision', 'follow_up', 'result'] as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        foreach ([
            'customerId' => 'customer_id',
            'orderId' => 'order_id',
            'orderServiceId' => 'order_service_id',
            'tourId' => 'tour_id',
            'claimType' => 'claim_type',
            'status' => 'status',
            'responsibleUserId' => 'responsible_user_id',
        ] as $input => $column) {
            if ($request->filled($input) && ! array_key_exists($column, $scoped)) {
                $query->where($column, $request->validated($input));
            }
        }

        foreach ([
            'createdFrom' => ['created_at', '>='],
            'createdTo' => ['created_at', '<='],
            'closedFrom' => ['closed_at', '>='],
            'closedTo' => ['closed_at', '<='],
        ] as $input => [$column, $operator]) {
            if ($request->filled($input)) {
                $query->where($column, $operator, $request->validated($input));
            }
        }

        $sort = $request->getSort('created_at', self::SORTABLE);
        $direction = $request->validated('direction') ?? ($sort === 'created_at' ? 'desc' : 'asc');

        return $query->orderBy($sort, $direction)->paginate($request->getPerPage());
    }
}
