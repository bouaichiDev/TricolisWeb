<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\CustomerApiConfiguration;

/**
 * `rotateKey` est une permission distincte d'`update` : remplacer une clé coupe
 * immédiatement l'accès de toutes les intégrations qui l'utilisent. Ce n'est pas
 * une correction de saisie.
 */
class CustomerApiConfigurationPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'customer_api_configurations.view');
    }

    public function view(User $user, CustomerApiConfiguration $configuration): bool
    {
        return $this->hasPermission($user, $this->organizationOf($configuration), 'customer_api_configurations.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'customer_api_configurations.create');
    }

    public function update(User $user, CustomerApiConfiguration $configuration): bool
    {
        return $this->hasPermission($user, $this->organizationOf($configuration), 'customer_api_configurations.update');
    }

    public function delete(User $user, CustomerApiConfiguration $configuration): bool
    {
        return $this->hasPermission($user, $this->organizationOf($configuration), 'customer_api_configurations.delete');
    }

    public function rotateKey(User $user, CustomerApiConfiguration $configuration): bool
    {
        return $this->hasPermission($user, $this->organizationOf($configuration), 'customer_api_configurations.rotate_key');
    }

    private function organizationOf(CustomerApiConfiguration $configuration): ?string
    {
        return $configuration->customer?->organization_id;
    }
}
