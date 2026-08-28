<?php

declare(strict_types=1);

namespace App\Modules\Billing\Queries;

use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Models\OrderService;
use App\Shared\Http\Requests\ListRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

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
 *
 * **Les filtres de colonne sont ici, pas dans l'écran.** Une liste est paginée :
 * filtrer les vingt-cinq lignes affichées cacherait tout ce qui se trouve sur
 * les pages suivantes, et le facturier croirait avoir tout vu.
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
                'order:id,order_number,customer_reference,customer_id,currency_code',
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

        $this->filterColumns($query, $request);

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

    /**
     * Les filtres posés colonne par colonne.
     *
     * Chacun suit ce que la colonne montre : « Prestation » affiche un numéro
     * et un libellé de service, on cherche donc dans les deux. Ne chercher que
     * dans le numéro rendrait le champ inutile pour qui tape « Livraison ».
     *
     * @param  Builder<OrderService>  $query
     */
    private function filterColumns(Builder $query, ListRequest $request): void
    {
        if ($request->filled('service')) {
            $term = $request->validated('service');

            $query->where(fn (Builder $builder) => $builder
                ->where('service_number', 'like', "%{$term}%")
                ->orWhereHas('service', fn ($service) => $service
                    ->where('code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")));
        }

        if ($request->filled('order')) {
            $term = $request->validated('order');

            $query->whereHas('order', fn ($order) => $order
                ->where('order_number', 'like', "%{$term}%")
                ->orWhere('customer_reference', 'like', "%{$term}%"));
        }

        if ($request->filled('address')) {
            $term = $request->validated('address');

            $query->whereHas('address', fn ($address) => $address
                ->where('city', 'like', "%{$term}%")
                ->orWhere('postal_code', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%"));
        }

        foreach ([
            'quantityMin' => ['quantity', '>='],
            'quantityMax' => ['quantity', '<='],
            'priceMin' => ['customer_unit_price', '>='],
            'priceMax' => ['customer_unit_price', '<='],
        ] as $input => [$column, $operator]) {
            if ($request->filled($input)) {
                $query->where($column, $operator, $request->validated($input));
            }
        }
    }
}
