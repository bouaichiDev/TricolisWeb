<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Identity\Models\User;

class AgencyPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'agencies.view');
    }

    public function view(User $user, Agency $agency): bool
    {
        return $this->hasPermission($user, $agency->organization_id, 'agencies.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'agencies.create');
    }

    public function update(User $user, Agency $agency): bool
    {
        return $this->hasPermission($user, $agency->organization_id, 'agencies.update');
    }

    public function delete(User $user, Agency $agency): bool
    {
        return $this->hasPermission($user, $agency->organization_id, 'agencies.delete');
    }
}
