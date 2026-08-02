<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Identity\Models\User;

class EntityAddressPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'addresses.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'addresses.create');
    }

    public function update(User $user, EntityAddress $entityAddress): bool
    {
        return $this->hasPermission($user, $entityAddress->organization_id, 'addresses.update');
    }

    public function delete(User $user, EntityAddress $entityAddress): bool
    {
        return $this->hasPermission($user, $entityAddress->organization_id, 'addresses.delete');
    }
}
