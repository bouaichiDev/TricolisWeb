<?php

declare(strict_types=1);

namespace App\Modules\Tours\Services;

use App\Modules\Tours\Models\Tour;

/**
 * Contrôle les six références d'une tournée, création comme modification.
 *
 * Les contraintes du §8 sont croisées : le dépôt dépend de l'agence, le
 * chauffeur et le véhicule dépendent du fournisseur. Un `PATCH` qui ne change
 * que le chauffeur doit donc être vérifié contre le fournisseur **déjà
 * enregistré**, pas contre rien. C'est tout l'objet de cette classe : composer
 * les valeurs entrantes avec celles de la tournée existante avant de contrôler.
 */
final readonly class TourReferenceResolver
{
    public function __construct(private TourScopeGuard $guard) {}

    /**
     * @param  array<string, mixed>  $attributes  colonnes SQL fournies
     */
    public function assert(array $attributes, string $organizationId, ?Tour $existing = null): void
    {
        $agencyId = $this->resolve($attributes, 'agency_id', $existing);
        $providerId = $this->resolve($attributes, 'provider_id', $existing);

        if ($agencyId !== null && $this->isProvided($attributes, 'agency_id', $existing)) {
            $this->guard->agency($agencyId, $organizationId);
        }

        $depotId = $this->resolve($attributes, 'depot_id', $existing);
        if ($depotId !== null && $agencyId !== null) {
            $this->guard->depot($depotId, $agencyId);
        }

        if ($providerId !== null && array_key_exists('provider_id', $attributes)) {
            $this->guard->provider($providerId, $organizationId);
        }

        $driverId = $this->resolve($attributes, 'driver_id', $existing);
        if ($driverId !== null && $this->touches($attributes, ['driver_id', 'provider_id'])) {
            $this->guard->driver($driverId, $providerId, $organizationId);
        }

        $vehicleId = $this->resolve($attributes, 'vehicle_id', $existing);
        if ($vehicleId !== null && $this->touches($attributes, ['vehicle_id', 'provider_id'])) {
            $this->guard->vehicle($vehicleId, $providerId, $organizationId);
        }
    }

    /**
     * Valeur effective d'une colonne : celle fournie si elle l'est, sinon celle
     * déjà enregistrée.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function resolve(array $attributes, string $column, ?Tour $existing): ?string
    {
        if (array_key_exists($column, $attributes)) {
            return $attributes[$column];
        }

        return $existing?->getAttribute($column);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function isProvided(array $attributes, string $column, ?Tour $existing): bool
    {
        return array_key_exists($column, $attributes) || $existing === null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $columns
     */
    private function touches(array $attributes, array $columns): bool
    {
        foreach ($columns as $column) {
            if (array_key_exists($column, $attributes)) {
                return true;
            }
        }

        return false;
    }
}
