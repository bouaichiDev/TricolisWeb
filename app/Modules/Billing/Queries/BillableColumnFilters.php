<?php

declare(strict_types=1);

namespace App\Modules\Billing\Queries;

use App\Modules\Orders\Models\OrderService;
use App\Shared\Http\Requests\ListRequest;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les filtres posés colonne par colonne sur les prestations facturables.
 *
 * **Ils vivent côté serveur.** Une liste est paginée : filtrer les vingt-cinq
 * lignes affichées cacherait tout ce qui se trouve sur les pages suivantes, et
 * le facturier croirait avoir tout vu.
 *
 * Chaque filtre suit ce que sa colonne **montre**. « Commande » n'affiche que
 * le numéro depuis que la référence a la sienne : y chercher aussi la référence
 * rendrait un résultat que la colonne ne justifie pas.
 */
final readonly class BillableColumnFilters
{
    /**
     * @param  Builder<OrderService>  $query
     */
    public function apply(Builder $query, ListRequest $request): void
    {
        // « Prestation » montre un numero et un libelle de service : ne
        // chercher que dans le numero rendrait le champ inutile a qui tape
        // « Livraison ».
        $this->term($query, $request, 'service', fn (Builder $builder, string $term) => $builder
            ->where(fn (Builder $nested) => $nested
                ->where('service_number', 'like', "%{$term}%")
                ->orWhereHas('service', fn ($service) => $service
                    ->where('code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%"))));

        $this->term($query, $request, 'order', fn (Builder $builder, string $term) => $builder
            ->whereHas('order', fn ($order) => $order->where('order_number', 'like', "%{$term}%")));

        $this->term($query, $request, 'reference', fn (Builder $builder, string $term) => $builder
            ->whereHas('order', fn ($order) => $order
                ->where('customer_reference', 'like', "%{$term}%")));

        $this->term($query, $request, 'address', fn (Builder $builder, string $term) => $builder
            ->whereHas('address', fn ($address) => $address
                ->where('city', 'like', "%{$term}%")
                ->orWhere('postal_code', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")));

        $this->bounds($query, $request);
    }

    /**
     * @param  Builder<OrderService>  $query
     * @param  callable(Builder<OrderService>, string): mixed  $apply
     */
    private function term(Builder $query, ListRequest $request, string $input, callable $apply): void
    {
        if ($request->filled($input)) {
            $apply($query, (string) $request->validated($input));
        }
    }

    /**
     * Les intervalles numériques.
     *
     * Deux bornes plutôt qu'une égalité : un prix décimal ne se saisit pas
     * exactement, et « au moins 100 » est la question qu'on se pose vraiment.
     *
     * @param  Builder<OrderService>  $query
     */
    private function bounds(Builder $query, ListRequest $request): void
    {
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
