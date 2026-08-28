<?php

declare(strict_types=1);

namespace App\Modules\Billing\Queries;

use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Models\OrderService;
use App\Shared\Http\Requests\ListRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Les prestations qu'on peut encore facturer à un client.
 *
 * **Le serveur décide, pas l'écran.** Le §42 l'interdit explicitement : une
 * règle d'éligibilité recopiée dans React finirait par diverger, et proposerait
 * des services que la création refuserait.
 *
 * **La règle vient du statut du service, vérifié et non supposé.** Le §43
 * défend de coder en dur `status == COMPLETED` sans regarder : le relevé donne
 * neuf statuts, dont `invoiced`, que le projet avait déjà prévu pour marquer une
 * prestation facturée.
 *
 * Est facturable ce qui a été **fait** — `completed` — et qui ne l'est pas
 * encore. Un service `failed` ou `cancelled` ne l'est pas : on ne facture pas ce
 * qu'on n'a pas livré. Un service `invoiced` non plus, par construction.
 *
 * L'unicité de `invoice_lines.order_service_id` protège de toute façon le §10.
 * Ce filtre évite seulement de proposer ce qui sera refusé — un écran qui offre
 * un choix impossible use la confiance.
 */
final readonly class BillableServiceQuery
{
    public function paginate(ListRequest $request, string $customerId, string $organizationId): LengthAwarePaginator
    {
        $query = OrderService::query()
            ->where('status', OrderServiceStatus::COMPLETED->value)
            ->whereDoesntHave('invoiceLine')
            ->whereHas('order', fn ($order) => $order
                ->where('customer_id', $customerId)
                ->where('organization_id', $organizationId))
            ->with([
                'order:id,order_number,customer_reference,customer_id',
                'service:id,code,name',
                'address',
            ]);

        // La periode porte sur la date demandee du service : c'est elle qui dit
        // quand la prestation devait avoir lieu, et c'est sur elle que porte la
        // periode de facturation.
        if ($request->filled('periodFrom')) {
            $query->whereDate('requested_date', '>=', $request->validated('periodFrom'));
        }

        if ($request->filled('periodTo')) {
            $query->whereDate('requested_date', '<=', $request->validated('periodTo'));
        }

        if ($request->filled('search')) {
            $search = $request->validated('search');

            $query->where(fn ($builder) => $builder
                ->where('service_number', 'like', "%{$search}%")
                ->orWhereHas('order', fn ($order) => $order
                    ->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_reference', 'like', "%{$search}%")));
        }

        return $query
            ->orderBy('requested_date')
            ->orderBy('service_number')
            ->paginate($request->getPerPage());
    }
}
