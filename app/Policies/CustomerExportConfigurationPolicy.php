<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Identity\Models\User;

class CustomerExportConfigurationPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'customer_export_configurations.view');
    }

    public function view(User $user, CustomerExportConfiguration $configuration): bool
    {
        return $this->hasPermission($user, $this->organizationOf($configuration), 'customer_export_configurations.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'customer_export_configurations.create');
    }

    public function update(User $user, CustomerExportConfiguration $configuration): bool
    {
        return $this->hasPermission($user, $this->organizationOf($configuration), 'customer_export_configurations.update');
    }

    public function delete(User $user, CustomerExportConfiguration $configuration): bool
    {
        return $this->hasPermission($user, $this->organizationOf($configuration), 'customer_export_configurations.delete');
    }

    private function organizationOf(CustomerExportConfiguration $configuration): ?string
    {
        return $configuration->customer?->organization_id;
    }
}
