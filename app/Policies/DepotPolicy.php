<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Agencies\Models\Depot;
use App\Modules\Identity\Models\User;

class DepotPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'depots.view');
    }

    public function view(User $user, Depot $depot): bool
    {
        return $this->hasPermission($user, $depot->agency->organization_id, 'depots.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'depots.create');
    }

    public function update(User $user, Depot $depot): bool
    {
        return $this->hasPermission($user, $depot->agency->organization_id, 'depots.update');
    }

    public function delete(User $user, Depot $depot): bool
    {
        return $this->hasPermission($user, $depot->agency->organization_id, 'depots.delete');
    }
}
