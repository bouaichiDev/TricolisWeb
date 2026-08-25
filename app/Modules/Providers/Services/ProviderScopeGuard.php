<?php

declare(strict_types=1);

namespace App\Modules\Providers\Services;

use App\Modules\Providers\Models\Provider;
use App\Modules\Types\Models\TypeItem;
use Illuminate\Validation\ValidationException;

/**
 * Vérifie que les références d'une ressource de la Phase 3 sont accessibles
 * dans l'organisation active.
 *
 * Les identifiants envoyés par le client ne sont jamais crus sur parole : un
 * `exists:providers,id` seul laisserait passer le fournisseur d'une autre
 * organisation. Tous les contrôles passent par ici, en un seul endroit.
 */
final readonly class ProviderScopeGuard
{
    public function provider(string $providerId, string $organizationId, string $field = 'providerId'): Provider
    {
        $provider = Provider::where('organization_id', $organizationId)->whereKey($providerId)->first();

        if ($provider === null) {
            $this->fail($field, 'Ce fournisseur n’appartient pas à l’organisation active.');
        }

        return $provider;
    }

    /**
     * Le type de véhicule est une valeur du référentiel `vehicle`.
     *
     * Depuis la fusion des référentiels, la table héberge aussi les types de
     * colis : sans le filtre sur la source, « Palette » passerait pour un type
     * de véhicule valide.
     */
    public function vehicleType(string $vehicleTypeId, string $organizationId, string $field = 'vehicleTypeId'): TypeItem
    {
        $type = TypeItem::where('organization_id', $organizationId)
            ->whereKey($vehicleTypeId)
            ->whereHas('type', fn ($query) => $query->where('code', 'vehicle'))
            ->first();

        if ($type === null) {
            $this->fail($field, 'Ce type de véhicule n’appartient pas à l’organisation active.');
        }

        return $type;
    }

    /**
     * Un véhicule ne peut pas croiser le fournisseur d'une organisation avec le
     * type de véhicule d'une autre.
     */
    public function assertSameOrganization(Provider $provider, TypeItem $vehicleType): void
    {
        if ($provider->organization_id !== $vehicleType->organization_id) {
            $this->fail('vehicleTypeId', 'Le fournisseur et le type de véhicule doivent appartenir à la même organisation.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
