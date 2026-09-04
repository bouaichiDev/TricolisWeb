<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Services;

use App\Modules\Orders\Models\OrderService;
use App\Modules\Pricing\Models\PricingVariable;
use Illuminate\Support\Collection;

/**
 * Ce qu'une prestation apporte au calcul.
 *
 * **Une liste blanche, pas un accès au modèle.** Le §169F et le §67 le
 * demandent : une formule ne doit pas pouvoir descendre dans Eloquent. Le
 * contexte est donc un tableau plat dont les clés sont exactement les variables
 * déclarées au catalogue de la plateforme.
 *
 * **Le catalogue est une donnée, le chemin reste du code.** Le superadmin
 * décide quelles sources deviennent des variables et sous quel nom ; c'est
 * `PricingVariableSources` qui sait aller les chercher. Un administrateur
 * d'organisme, lui, ne fait que les employer.
 *
 * Les valeurs viennent de **la prestation** quand la source le dit (§169K) : un
 * chargement et une livraison n'ont pas le même poids, et prendre le total de
 * la commande facturerait deux fois le même colis. Les sources au niveau
 * commande existent, mais elles se choisissent en connaissance de cause.
 */
final class PricingContext
{
    /** @var Collection<int, PricingVariable>|null */
    private ?Collection $catalogue = null;

    public function __construct(private readonly PricingVariableSources $sources) {}

    /**
     * @return array<string, string|null>
     */
    public function build(OrderService $service): array
    {
        $context = [];

        foreach ($this->variables() as $variable) {
            $context[$variable->code] = $this->sources->value($variable->source_key, $service);
        }

        return $context;
    }

    /**
     * Les noms qu'une formule peut employer.
     *
     * @return list<string>
     */
    public function numericNames(): array
    {
        return $this->variables()->where('kind', PricingVariableSources::NUMERIC)
            ->pluck('code')->values()->all();
    }

    /**
     * Les dimensions qui filtrent une règle ou une zone.
     *
     * @return list<string>
     */
    public function dimensionNames(): array
    {
        return $this->variables()->where('kind', PricingVariableSources::DIMENSION)
            ->pluck('code')->values()->all();
    }

    /**
     * Les seules valeurs qu'on passe à l'évaluateur.
     *
     * Les dimensions n'y sont pas : `code_postal` n'a pas à être multiplié, et
     * l'y laisser inviterait à écrire des formules qui n'ont pas de sens.
     *
     * @param  array<string, string|null>  $context
     * @return array<string, string|null>
     */
    public function numeric(array $context): array
    {
        return array_intersect_key($context, array_flip($this->numericNames()));
    }

    /**
     * Le catalogue actif, lu une fois par requête.
     *
     * Il change rarement et se relit à chaque ligne de facture : le garder sur
     * l'instance évite une requête par prestation sur une page de
     * préfacturation. Sur l'instance et non en statique — une mémoire globale
     * survivrait d'un test à l'autre, et cacherait les variables créées entre
     * deux.
     *
     * @return Collection<int, PricingVariable>
     */
    private function variables(): Collection
    {
        return $this->catalogue ??= PricingVariable::query()->usable()->get();
    }
}
