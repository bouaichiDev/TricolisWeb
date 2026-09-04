<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\OrganizationApiConfiguration;

/**
 * Ce que porte l'organisme, l'organisme le gouverne.
 *
 * Rien de plateforme ici : ces réglages décrivent la façon dont **un** organisme
 * travaille, pas le domaine commun.
 */
class OrganizationApiConfigurationPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'api_configurations.view');
    }

    public function view(User $user, OrganizationApiConfiguration $model): bool
    {
        return $this->hasPermission($user, $model->organization_id, 'api_configurations.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'api_configurations.create');
    }

    public function update(User $user, OrganizationApiConfiguration $model): bool
    {
        return $this->hasPermission($user, $model->organization_id, 'api_configurations.update');
    }

    public function delete(User $user, OrganizationApiConfiguration $model): bool
    {
        return $this->hasPermission($user, $model->organization_id, 'api_configurations.delete');
    }
}
