<?php

declare(strict_types=1);

namespace App\Modules\Billing\Queries;

use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\Service;
use App\Shared\Http\Requests\ListRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
    public function __construct(private BillableColumnFilters $filters) {}

    /** Au-dela, une liste de suggestions ne se lit plus : elle se refiltre. */
    private const int SUGGESTIONS = 15;

    public function paginate(ListRequest $request, string $customerId, string $organizationId): LengthAwarePaginator
    {
        $query = $this->eligible($customerId, $organizationId)
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

        $this->filters->apply($query, $request);

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
     * Les valeurs qui existent vraiment, pour compléter une saisie.
     *
     * **Elles se cherchent dans l'ensemble éligible, pas dans la page.** C'est
     * tout l'intérêt : un facturier qui tape « 000907 » veut savoir si ce
     * numéro existe quelque part, pas s'il figure parmi les vingt-cinq lignes
     * sous ses yeux.
     *
     * Rien d'autre que des numéros n'est proposé : une suggestion doit pouvoir
     * être collée telle quelle dans le filtre et rendre exactement la ligne
     * qu'on visait.
     *
     * @return list<string>
     */
    public function suggest(string $field, ?string $term, string $customerId, string $organizationId): array
    {
        $eligible = $this->eligible($customerId, $organizationId);
        $term = $term === null ? '' : trim($term);

        $values = $field === 'order'
            ? $this->orderNumbers($eligible, $term)
            : $this->serviceLabels($eligible, $term);

        return $values->filter()->unique()->take(self::SUGGESTIONS)->values()->all();
    }

    /**
     * Les numéros de commande à proposer.
     *
     * @param  Builder<OrderService>  $eligible
     * @return Collection<int, string>
     */
    private function orderNumbers(Builder $eligible, string $term): Collection
    {
        return Order::query()
            ->whereIn('id', $eligible->clone()->select('order_id'))
            ->when($term !== '', fn ($query) => $query->where('order_number', 'like', "%{$term}%"))
            ->orderBy('order_number')
            ->limit(self::SUGGESTIONS)
            ->pluck('order_number');
    }

    /**
     * Ce qu'on peut proposer pour la colonne « Prestation ».
     *
     * **Le libellé du service d'abord, son numéro ensuite.** La colonne montre
     * les deux, et le filtre cherche dans les deux : ne suggérer que des
     * numéros laissait sans réponse celui qui tape « livraison », alors que la
     * liste répondait. Une complétion muette là où le filtre fonctionne se lit
     * comme une panne.
     *
     * Les libellés sont peu nombreux et se retiennent ; les numéros sont
     * innombrables et ne servent qu'une fois la saisie commencée.
     *
     * @param  Builder<OrderService>  $eligible
     * @return Collection<int, string>
     */
    private function serviceLabels(Builder $eligible, string $term): Collection
    {
        $labels = Service::query()
            ->whereIn('id', $eligible->clone()->select('service_id'))
            ->when($term !== '', fn ($query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(self::SUGGESTIONS)
            ->pluck('name');

        if ($term === '') {
            return $labels;
        }

        $numbers = $eligible->clone()
            ->where('service_number', 'like', "%{$term}%")
            ->orderBy('service_number')
            ->limit(self::SUGGESTIONS)
            ->pluck('service_number');

        return $labels->concat($numbers);
    }

    /**
     * Ce qui reste à facturer chez ce client, avant tout filtre.
     *
     * Une seule définition de l'éligibilité : la liste et les suggestions
     * doivent s'accorder, sans quoi l'écran proposerait de compléter avec un
     * numéro qu'il refuserait ensuite d'afficher.
     *
     * @return Builder<OrderService>
     */
    private function eligible(string $customerId, string $organizationId): Builder
    {
        return OrderService::query()
            ->where('status', OrderServiceStatus::COMPLETED->value)
            ->whereDoesntHave('invoiceLine')
            ->whereHas('order', fn ($order) => $order
                ->where('customer_id', $customerId)
                ->where('organization_id', $organizationId));
    }
}
