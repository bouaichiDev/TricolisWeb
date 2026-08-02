<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\CustomerImportConfiguration;

/**
 * La configuration n'a pas d'organisation propre : sa permission est évaluée
 * dans celle de son client.
 */
class CustomerImportConfigurationPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'customer_import_configurations.view');
    }

    public function view(User $user, CustomerImportConfiguration $configuration): bool
    {
        return $this->hasPermission($user, $this->organizationOf($configuration), 'customer_import_configurations.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'customer_import_configurations.create');
    }

    public function update(User $user, CustomerImportConfiguration $configuration): bool
    {
        return $this->hasPermission($user, $this->organizationOf($configuration), 'customer_import_configurations.update');
    }

    public function delete(User $user, CustomerImportConfiguration $configuration): bool
    {
        return $this->hasPermission($user, $this->organizationOf($configuration), 'customer_import_configurations.delete');
    }

    private function organizationOf(CustomerImportConfiguration $configuration): ?string
    {
        return $configuration->customer?->organization_id;
    }
}
