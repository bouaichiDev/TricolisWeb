<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Identity\Models\User;

/**
 * Le véhicule porte son organisation : la permission s'évalue dessus, sans
 * charger le fournisseur.
 *
 * Elle passait par lui tant qu'il était obligatoire. Depuis qu'un véhicule peut
 * appartenir en propre à l'organisation — phase 4 — ce détour rendait `null`
 * pour tout véhicule sans fournisseur, et sa fiche répondait 403 même au
 * propriétaire de l'organisation.
 */
class VehiclePolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'vehicles.view');
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $this->hasPermission($user, $this->organizationOf($vehicle), 'vehicles.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'vehicles.create');
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $this->hasPermission($user, $this->organizationOf($vehicle), 'vehicles.update');
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->hasPermission($user, $this->organizationOf($vehicle), 'vehicles.delete');
    }

    private function organizationOf(Vehicle $vehicle): ?string
    {
        return $vehicle->organization_id;
    }
}
