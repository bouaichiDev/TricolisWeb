<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Services;

use App\Modules\Orders\Models\OrderService;

/**
 * Ce qu'une prestation apporte au calcul.
 *
 * **Une liste blanche, pas un accès au modèle.** Le §169F et le §67 le
 * demandent : une formule ne doit pas pouvoir descendre dans Eloquent. Le
 * contexte est donc un tableau plat, construit ici et nulle part ailleurs, dont
 * les clés sont exactement celles qu'une formule peut nommer.
 *
 * Les valeurs viennent de **la prestation**, pas de la commande (§169K) : un
 * chargement et une livraison n'ont pas le même poids, et prendre le total de
 * la commande facturerait deux fois le même colis.
 *
 * `distance` reste nulle tant qu'aucune distance par prestation n'existe : la
 * tournée en porte une, mais elle vaut pour le trajet entier, pas pour un
 * arrêt. Une formule qui la nomme échoue clairement plutôt que de rendre un
 * prix bâti sur la mauvaise valeur.
 */
final readonly class PricingContext
{
    /**
     * Les paramètres numériques qu'une formule peut nommer.
     *
     * @var list<string>
     */
    public const array VARIABLES = [
        'poids',
        'volume',
        'quantite',
        'nombre_colis',
        'duree',
        'distance',
    ];

    /**
     * Les dimensions qui filtrent — conditions et matrices — sans se multiplier.
     *
     * @var list<string>
     */
    public const array DIMENSIONS = [
        'code_postal',
        'ville',
        'pays',
        'service',
    ];

    /**
     * @return array<string, string|null>
     */
    public function build(OrderService $service): array
    {
        $address = $service->address;

        return [
            'poids' => $this->number($service->weight),
            'volume' => $this->number($service->volume),
            'quantite' => $this->number($service->quantity),
            'nombre_colis' => $this->number($service->package_count),
            'duree' => $this->number($service->required_time_minutes),
            // Aucune distance n'est portee par une prestation : la laisser
            // nulle fait echouer clairement une formule qui la reclame.
            'distance' => null,

            'code_postal' => $address?->postal_code,
            'ville' => $address?->city,
            'pays' => $address?->country,
            'service' => $service->service?->code,
        ];
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
        return array_intersect_key($context, array_flip(self::VARIABLES));
    }

    private function number(mixed $value): ?string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (string) $value;
    }
}
