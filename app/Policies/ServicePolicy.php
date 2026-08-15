<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Service;

class ServicePolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'services.view');
    }

    public function view(User $user, Service $service): bool
    {
        return $this->hasPermission($user, $service->organization_id, 'services.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'services.create');
    }

    public function update(User $user, Service $service): bool
    {
        return $this->hasPermission($user, $service->organization_id, 'services.update');
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->hasPermission($user, $service->organization_id, 'services.delete');
    }
}
