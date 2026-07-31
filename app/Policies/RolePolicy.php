<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;

class RolePolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->hasPermission($user, $role->organization_id, 'roles.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->hasPermission($user, $role->organization_id, 'roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->hasPermission($user, $role->organization_id, 'roles.delete');
    }
}
