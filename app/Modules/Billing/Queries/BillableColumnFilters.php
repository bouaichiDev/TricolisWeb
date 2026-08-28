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
        $this->services($query, $request);

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
     * La colonne « Prestation », qui admet plusieurs valeurs.
     *
     * **Elles se cumulent en « ou ».** Retenir les livraisons *et* les
     * chargements est une seule question ; les additionner en « et » ne
     * rendrait jamais rien, aucune prestation n'étant les deux à la fois.
     *
     * Chaque valeur cherche dans le numéro comme dans le libellé : la colonne
     * montre les deux, et ne chercher que dans le numéro rendrait le champ
     * inutile à qui choisit « Livraison ».
     *
     * @param  Builder<OrderService>  $query
     */
    private function services(Builder $query, ListRequest $request): void
    {
        if (! $request->filled('service')) {
            return;
        }

        /** @var list<string> $terms */
        $terms = array_filter((array) $request->validated('service'));

        if ($terms === []) {
            return;
        }

        $query->where(function (Builder $group) use ($terms): void {
            foreach ($terms as $term) {
                $group->orWhere(fn (Builder $nested) => $nested
                    ->where('service_number', 'like', "%{$term}%")
                    ->orWhereHas('service', fn ($service) => $service
                        ->where('code', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%")));
            }
        });
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
