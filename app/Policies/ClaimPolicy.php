<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Claims\Models\Claim;
use App\Modules\Identity\Models\User;

class ClaimPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'claims.view');
    }

    public function view(User $user, Claim $claim): bool
    {
        return $this->hasPermission($user, $claim->organization_id, 'claims.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'claims.create');
    }

    public function update(User $user, Claim $claim): bool
    {
        return $this->hasPermission($user, $claim->organization_id, 'claims.update');
    }

    public function delete(User $user, Claim $claim): bool
    {
        return $this->hasPermission($user, $claim->organization_id, 'claims.delete');
    }
}
