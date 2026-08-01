<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Identity\Models\User;

/**
 * Le véhicule n'a pas d'organisation propre : sa permission est évaluée dans
 * l'organisation de son fournisseur.
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
        return $vehicle->provider?->organization_id;
    }
}
