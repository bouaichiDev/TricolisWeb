<?php

declare(strict_types=1);

namespace App\Modules\Planning\Services;

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Depot;
use App\Shared\Database\MorphMap;

/**
 * L'adresse d'où part une tournée.
 *
 * Le dépôt est le point de départ opérationnel (§59). Il ne porte pas de
 * colonne d'adresse : il en reçoit une par les liaisons polymorphes, comme un
 * client ou une agence — mécanisme qui accepte déjà `depot`, et qu'il suffit
 * d'employer. Ajouter une colonne créerait un second mécanisme d'adresse à
 * maintenir en parallèle.
 *
 * **Aucun repli sur l'agence.** Un itinéraire qui partirait du siège au lieu de
 * l'entrepôt serait faux de plusieurs kilomètres sans que personne le voie ; il
 * vaut mieux dire que le dépôt n'a pas d'adresse.
 */
final readonly class DepotLocation
{
    public function __construct(private GeocodingService $geocoding) {}

    /**
     * Adresse rattachée au dépôt, la principale s'il y en a une.
     *
     * Plusieurs liaisons sont possibles — quai de départ, bureau. Celle qui
     * porte `is_default` l'emporte ; à défaut, la première par identifiant.
     * `entity_addresses` n'a pas d'horodatage, mais ses clés sont des ULID :
     * leur ordre est celui de la création.
     */
    public function addressOf(Depot $depot): ?Address
    {
        $link = EntityAddress::where('entity_type', MorphMap::DEPOT)
            ->where('entity_id', $depot->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        return $link === null ? null : Address::find($link->address_id);
    }

    /**
     * Coordonnées du départ, géocodées au besoin.
     *
     * @return array{0: float, 1: float}|null
     */
    public function coordinatesOf(Depot $depot, string $organizationId): ?array
    {
        $address = $this->addressOf($depot);

        if ($address === null) {
            return null;
        }

        if (! $this->geocoding->locate($address, $organizationId)) {
            return null;
        }

        $address->refresh();

        return [(float) $address->latitude, (float) $address->longitude];
    }
}
