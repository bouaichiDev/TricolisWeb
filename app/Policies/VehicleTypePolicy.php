<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Identity\Models\User;

class VehicleTypePolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'vehicle_types.view');
    }

    public function view(User $user, VehicleType $vehicleType): bool
    {
        return $this->hasPermission($user, $vehicleType->organization_id, 'vehicle_types.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'vehicle_types.create');
    }

    public function update(User $user, VehicleType $vehicleType): bool
    {
        return $this->hasPermission($user, $vehicleType->organization_id, 'vehicle_types.update');
    }

    public function delete(User $user, VehicleType $vehicleType): bool
    {
        return $this->hasPermission($user, $vehicleType->organization_id, 'vehicle_types.delete');
    }
}
