<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;

class OrganizationPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->hasOrganizationAccess($user, $organization->id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization->id, 'organizations.update')
            || $this->isOwner($user, $organization->id);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->isOwner($user, $organization->id);
    }

    private function isOwner(User $user, string $organizationId): bool
    {
        return OrganizationUser::where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->where('is_owner', true)
            ->exists();
    }
}
