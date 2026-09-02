<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\OrganizationUser;

class OrganizationUserPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'users.view');
    }

    public function view(User $user, OrganizationUser $membership): bool
    {
        return $this->hasPermission($user, $membership->organization_id, 'users.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'users.create');
    }

    public function update(User $user, OrganizationUser $membership): bool
    {
        return $this->hasPermission($user, $membership->organization_id, 'users.update');
    }

    public function delete(User $user, OrganizationUser $membership): bool
    {
        return $this->hasPermission($user, $membership->organization_id, 'users.disable');
    }

    /**
     * Rendre l'acces a un membre : lien de reinitialisation, ou mot de passe pose.
     *
     * Sa propre fiche est exclue : un administrateur change son mot de passe par
     * son profil, ou l'oublie et passe par la procedure publique. Se le poser
     * ici contournerait la verification du mot de passe actuel.
     */
    public function resetPassword(User $user, OrganizationUser $membership): bool
    {
        return $membership->user_id !== $user->id
            && $this->hasPermission($user, $membership->organization_id, 'users.reset_password');
    }
}
