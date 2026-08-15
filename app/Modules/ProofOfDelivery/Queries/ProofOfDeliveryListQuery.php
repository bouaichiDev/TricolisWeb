<?php

declare(strict_types=1);

namespace App\Modules\ProofOfDelivery\Queries;

use App\Http\Requests\Api\V1\ProofOfDelivery\ListProofOfDeliveryRequest;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche paginée des preuves de livraison.
 *
 * Le périmètre passe par la commande — `proofs_of_delivery` ne porte pas
 * d'`organization_id` — et le scope `inOrganization` du modèle est appliqué en
 * premier, sans condition.
 */
final readonly class ProofOfDeliveryListQuery
{
    /** @var list<string> */
    private const array SORTABLE = ['delivered_at', 'recipient_name'];

    /**
     * @param  array<string, string>  $scoped
     */
    public function paginate(ListProofOfDeliveryRequest $request, string $organizationId, array $scoped = []): LengthAwarePaginator
    {
        $query = ProofOfDelivery::inOrganization($organizationId);

        foreach ($scoped as $column => $value) {
            $query->where($column, $value);
        }

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder
                ->where('recipient_name', 'like', "%{$search}%")
                ->orWhere('remark', 'like', "%{$search}%"));
        }

        foreach ([
            'orderId' => 'order_id',
            'orderServiceId' => 'order_service_id',
            'tourStopId' => 'tour_stop_id',
            'createdBy' => 'created_by',
        ] as $input => $column) {
            if ($request->filled($input) && ! array_key_exists($column, $scoped)) {
                $query->where($column, $request->validated($input));
            }
        }

        if ($request->filled('deliveredFrom')) {
            $query->where('delivered_at', '>=', $request->validated('deliveredFrom'));
        }

        if ($request->filled('deliveredTo')) {
            $query->where('delivered_at', '<=', $request->validated('deliveredTo'));
        }

        $sort = $request->getSort('delivered_at', self::SORTABLE);
        $direction = $request->validated('direction') ?? ($sort === 'delivered_at' ? 'desc' : 'asc');

        return $query->orderBy($sort, $direction)->paginate($request->getPerPage());
    }
}
