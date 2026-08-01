<?php

declare(strict_types=1);

namespace App\Modules\Providers\Services;

use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Identity\Models\User;
use App\Modules\Providers\Models\Provider;
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

    public function vehicleType(string $vehicleTypeId, string $organizationId, string $field = 'vehicleTypeId'): VehicleType
    {
        $type = VehicleType::where('organization_id', $organizationId)->whereKey($vehicleTypeId)->first();

        if ($type === null) {
            $this->fail($field, 'Ce type de véhicule n’appartient pas à l’organisation active.');
        }

        return $type;
    }

    /**
     * Le compte lié à un chauffeur doit être membre de l'organisation active.
     */
    public function user(string $userId, string $organizationId, string $field = 'userId'): User
    {
        $user = User::whereKey($userId)
            ->whereHas('organizationUsers', fn ($query) => $query->where('organization_id', $organizationId))
            ->first();

        if ($user === null) {
            $this->fail($field, 'Cet utilisateur n’est pas accessible dans l’organisation active.');
        }

        return $user;
    }

    /**
     * Un véhicule ne peut pas croiser le fournisseur d'une organisation avec le
     * type de véhicule d'une autre.
     */
    public function assertSameOrganization(Provider $provider, VehicleType $vehicleType): void
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
