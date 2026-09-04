<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\OrganizationMailConfiguration;

/**
 * Qui règle la boîte d'envoi de l'organisation.
 *
 * Les mêmes permissions que les autres configurations d'intégration : c'est le
 * même geste d'administration, sur le même écran, et un rôle qui règle les API
 * externes règle aussi d'où partent les courriers.
 *
 * Il n'y a pas de `create` : la configuration est unique par organisation et
 * s'écrit d'un seul geste — la créer et la modifier sont la même autorisation.
 */
class OrganizationMailConfigurationPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'mail_configuration.view');
    }

    public function view(User $user, OrganizationMailConfiguration $model): bool
    {
        return $this->hasPermission($user, $model->organization_id, 'mail_configuration.view');
    }

    public function update(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'mail_configuration.update');
    }

    public function delete(User $user, OrganizationMailConfiguration $model): bool
    {
        return $this->hasPermission($user, $model->organization_id, 'mail_configuration.delete');
    }
}
