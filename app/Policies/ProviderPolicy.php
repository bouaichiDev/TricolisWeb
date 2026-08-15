<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Providers\Models\Provider;

class ProviderPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'providers.view');
    }

    public function view(User $user, Provider $provider): bool
    {
        return $this->hasPermission($user, $provider->organization_id, 'providers.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'providers.create');
    }

    public function update(User $user, Provider $provider): bool
    {
        return $this->hasPermission($user, $provider->organization_id, 'providers.update');
    }

    public function delete(User $user, Provider $provider): bool
    {
        return $this->hasPermission($user, $provider->organization_id, 'providers.delete');
    }
}
