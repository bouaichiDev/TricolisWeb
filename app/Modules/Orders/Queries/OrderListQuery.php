<?php

declare(strict_types=1);

namespace App\Modules\Orders\Queries;

use App\Http\Requests\Api\V1\Orders\ListOrderRequest;
use App\Modules\Orders\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Recherche paginée des commandes.
 *
 * Les filtres sont nombreux ; les regrouper ici évite un contrôleur illisible
 * et garantit qu'aucune requête ne part sans filtre d'organisation.
 */
final readonly class OrderListQuery
{
    /** @var list<string> */
    private const array SORTABLE = ['order_number', 'order_date', 'status', 'created_at'];

    public function paginate(ListOrderRequest $request, string $organizationId): LengthAwarePaginator
    {
        $query = Order::where('organization_id', $organizationId)
            ->with(['customer:id,code,name,status', 'agency:id,code,name'])
            ->withCount(['lines', 'orderServices']);

        $this->applySearch($query, $request);
        $this->applyScalarFilters($query, $request);
        $this->applyDateFilters($query, $request);
        $this->applyRelationFilters($query, $request);

        return $query
            ->orderBy($request->getSort('order_date', self::SORTABLE), $request->getDirection())
            ->paginate($request->getPerPage());
    }

    private function applySearch(mixed $query, ListOrderRequest $request): void
    {
        if (! $request->filled('search')) {
            return;
        }

        $search = $request->validated('search');
        $query->where(fn ($builder) => $builder
            ->where('order_number', 'like', "%{$search}%")
            ->orWhere('external_reference', 'like', "%{$search}%")
            ->orWhere('customer_reference', 'like', "%{$search}%"));
    }

    private function applyScalarFilters(mixed $query, ListOrderRequest $request): void
    {
        foreach ([
            'customerId' => 'customer_id',
            'agencyId' => 'agency_id',
            'depotId' => 'depot_id',
            'status' => 'status',
            'source' => 'source',
            'orderType' => 'order_type',
        ] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->validated($input));
            }
        }

        // `filled` refuserait `false` : ce filtre ne s'applique donc qu'a la
        // demande explicite, et « avec depot » n'est pas son contraire — c'est
        // l'absence de filtre.
        if ($request->boolean('withoutDepot')) {
            $query->whereNull('depot_id');
        }
    }

    /**
     * Les deux bornes sont **inclusives**, et la seconde couvre sa journée.
     *
     * `order_date` est un `dateTime`, pas une date : comparé tel quel, un
     * `createdTo=2026-09-03` valait « jusqu'au 3 septembre à minuit » et
     * écartait tout ce qui avait été commandé dans la journée. Le filtre rendait
     * donc une liste vide sur le jour même où la carte « Commandes par jour »
     * en comptait trente — et c'est précisément ce jour-là qu'on vient chercher
     * en cliquant sa colonne.
     *
     * `endOfDay()` sur la borne haute, jamais un `whereDate()` : la fonction
     * s'applique à la colonne et écarte l'index `(customer_id, order_date)`.
     */
    private function applyDateFilters(mixed $query, ListOrderRequest $request): void
    {
        if ($request->filled('createdFrom')) {
            $query->where('order_date', '>=', $request->date('createdFrom')->startOfDay());
        }

        if ($request->filled('createdTo')) {
            $query->where('order_date', '<=', $request->date('createdTo')->endOfDay());
        }

        // Date demandée : elle vit sur le service, pas sur la commande.
        if ($request->filled('requestedDate')) {
            $date = $request->validated('requestedDate');
            $query->whereHas('orderServices', fn ($builder) => $builder->whereDate('requested_date', $date));
        }
    }

    private function applyRelationFilters(mixed $query, ListOrderRequest $request): void
    {
        // Ville d'un service : le diagramme place l'adresse sur le service,
        // c'est donc là qu'on la cherche.
        if ($request->filled('city')) {
            $city = $request->validated('city');
            $query->whereHas('orderServices.address', fn ($builder) => $builder->where('city', 'like', "%{$city}%"));
        }

        if ($request->has('fromCatalog')) {
            $fromCatalog = $request->boolean('fromCatalog');
            $query->{$fromCatalog ? 'whereHas' : 'whereDoesntHave'}(
                'lines',
                fn ($builder) => $builder->whereNotNull('catalog_item_id'),
            );
        }
    }
}
