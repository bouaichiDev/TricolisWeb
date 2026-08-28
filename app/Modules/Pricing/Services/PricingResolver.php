<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Services;

use App\Modules\Pricing\DTOs\ResolvedPricing;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceMatrix;
use App\Modules\Pricing\Models\PriceRule;
use Illuminate\Support\Collection;

/**
 * Choisit le tarif qui s'applique à une prestation.
 *
 * **Le client d'abord, le global ensuite** (§169O, §169P). Un client sans tarif
 * propre n'est pas un client sans tarif : on retombe sur le barème général.
 * Une règle client partielle ne coupe pas ce repli — le §169CC l'interdit
 * explicitement — parce qu'un client qui a négocié la livraison ne renonce pas
 * pour autant au tarif du chargement.
 *
 * **La matrice avant la formule nue**, à portée égale : une matrice existe
 * précisément pour dire que le prix dépend de la zone, et l'ignorer au profit
 * d'une règle générale rendrait la matrice décorative.
 *
 * **Une règle citée par une matrice ne s'applique que par elle.** Sans cela, un
 * barème par zone serait décoratif : un code postal hors de toute zone
 * retomberait sur la même règle par la porte d'à côté, et les bornes ne
 * voudraient plus rien dire. Le lien se lit dans les données — aucune colonne
 * à tenir à jour.
 *
 * **Aucun « première ligne trouvée ».** Le §169AE l'interdit : à égalité de
 * priorité, on départage par la précision — une règle qui nomme le service
 * passe avant une règle générique — puis par le code, qui est stable. Deux
 * exécutions du même calcul donnent donc le même prix.
 */
final readonly class PricingResolver
{
    public function __construct(private ConditionMatcher $conditions) {}

    /**
     * @param  array<string, string|null>  $context
     */
    public function resolve(
        string $organizationId,
        string $customerId,
        ?string $serviceId,
        array $context,
        ?string $on = null,
    ): ?ResolvedPricing {
        $customerLists = $this->lists($organizationId, $on, $customerId);

        return $this->within($customerLists, $serviceId, $context)
            ?? $this->within($this->lists($organizationId, $on), $serviceId, $context);
    }

    /**
     * Les listes utilisables, du client ou globales.
     *
     * @return Collection<int, PriceList>
     */
    private function lists(string $organizationId, ?string $on, ?string $customerId = null): Collection
    {
        return PriceList::query()
            ->where('organization_id', $organizationId)
            ->usable($on)
            ->when(
                $customerId !== null,
                fn ($query) => $query
                    ->where('scope', PriceList::CUSTOMER)
                    ->whereHas('customers', fn ($customers) => $customers->whereKey($customerId)),
                fn ($query) => $query->where('scope', PriceList::GLOBAL),
            )
            ->get();
    }

    /**
     * Le meilleur tarif dans un ensemble de listes.
     *
     * @param  Collection<int, PriceList>  $lists
     * @param  array<string, string|null>  $context
     */
    private function within(Collection $lists, ?string $serviceId, array $context): ?ResolvedPricing
    {
        if ($lists->isEmpty()) {
            return null;
        }

        $ids = $lists->pluck('id')->all();
        $byId = $lists->keyBy('id');

        return $this->fromMatrix($ids, $byId, $serviceId, $context)
            ?? $this->fromRules($ids, $byId, $serviceId, $context);
    }

    /**
     * Une zone de matrice qui couvre la prestation, et la règle qu'elle désigne.
     *
     * @param  list<string>  $listIds
     * @param  Collection<string, PriceList>  $byId
     * @param  array<string, string|null>  $context
     */
    private function fromMatrix(array $listIds, Collection $byId, ?string $serviceId, array $context): ?ResolvedPricing
    {
        $matrices = PriceMatrix::query()
            ->whereIn('price_list_id', $listIds)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('service_id')->when(
                $serviceId !== null,
                fn ($nested) => $nested->orWhere('service_id', $serviceId),
            ))
            ->with(['rows.priceRule.conditions'])
            // La matrice qui nomme le service passe avant la generique.
            ->orderByRaw('service_id is null')
            ->orderBy('code')
            ->get();

        foreach ($matrices as $matrix) {
            $value = $context[$this->dimensionKey($matrix->dimension)] ?? null;

            foreach ($matrix->rows as $row) {
                $rule = $row->priceRule;

                if (! $row->covers($value) || $rule === null || ! $rule->is_active) {
                    continue;
                }

                if (! $this->conditions->matches($rule, $context)) {
                    continue;
                }

                return new ResolvedPricing($byId[$matrix->price_list_id], $rule, $matrix, $row);
            }
        }

        return null;
    }

    /**
     * Une règle sans matrice — le cas le plus courant (§169Z).
     *
     * @param  list<string>  $listIds
     * @param  Collection<string, PriceList>  $byId
     * @param  array<string, string|null>  $context
     */
    private function fromRules(array $listIds, Collection $byId, ?string $serviceId, array $context): ?ResolvedPricing
    {
        $rules = PriceRule::query()
            ->whereIn('price_list_id', $listIds)
            ->where('is_active', true)
            // Une regle citee par une matrice appartient a ses zones : la
            // reprendre ici la rendrait applicable hors de leurs bornes.
            ->whereDoesntHave('matrixRows')
            ->where(fn ($query) => $query->whereNull('service_id')->when(
                $serviceId !== null,
                fn ($nested) => $nested->orWhere('service_id', $serviceId),
            ))
            ->with('conditions')
            ->orderBy('priority')
            // A priorite egale, la regle qui nomme le service l'emporte ; puis
            // le code, stable, pour que deux calculs donnent le meme prix.
            ->orderByRaw('service_id is null')
            ->orderBy('code')
            ->get();

        foreach ($rules as $rule) {
            if ($this->conditions->matches($rule, $context)) {
                return new ResolvedPricing($byId[$rule->price_list_id], $rule);
            }
        }

        return null;
    }

    /** La clé du contexte que lit une dimension de matrice. */
    private function dimensionKey(string $dimension): string
    {
        return match ($dimension) {
            PriceMatrix::POSTAL_CODE => 'code_postal',
            default => $dimension,
        };
    }
}
