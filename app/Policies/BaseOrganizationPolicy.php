<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\OrganizationUser;
use Illuminate\Database\Eloquent\Model;

abstract class BaseOrganizationPolicy
{
    protected function hasOrganizationAccess(User $user, ?string $organizationId): bool
    {
        if ($organizationId === null) {
            return false;
        }

        return OrganizationUser::where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->exists();
    }

    protected function hasPermission(User $user, ?string $organizationId, string $permission): bool
    {
        if ($organizationId === null) {
            return false;
        }

        $organizationUser = OrganizationUser::where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->with('roles.permissions')
            ->first();

        if ($organizationUser === null) {
            return false;
        }

        if ($organizationUser->is_owner) {
            return true;
        }

        foreach ($organizationUser->roles as $role) {
            if ($role->permissions->contains('code', $permission)) {
                return true;
            }
        }

        return false;
    }

    protected function belongsToOrganization(Model $model, string $organizationId): bool
    {
        return $model->getAttribute('organization_id') === $organizationId;
    }
}
