<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Drivers\Models\Driver;
use App\Modules\Identity\Models\User;

/**
 * Le chauffeur porte son organisation : la permission s'évalue dessus, sans
 * charger le fournisseur.
 */
class DriverPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'drivers.view');
    }

    public function view(User $user, Driver $driver): bool
    {
        return $this->hasPermission($user, $this->organizationOf($driver), 'drivers.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'drivers.create');
    }

    public function update(User $user, Driver $driver): bool
    {
        return $this->hasPermission($user, $this->organizationOf($driver), 'drivers.update');
    }

    public function delete(User $user, Driver $driver): bool
    {
        return $this->hasPermission($user, $this->organizationOf($driver), 'drivers.delete');
    }

    private function organizationOf(Driver $driver): ?string
    {
        return $driver->organization_id;
    }
}
