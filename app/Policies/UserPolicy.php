<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\OrganizationUser;

/**
 * Un utilisateur n'est visible que par les organisations dont il est membre.
 */
class UserPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $this->sharesOrganization($user, $target, 'users.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $this->sharesOrganization($user, $target, 'users.update');
    }

    public function disable(User $user, User $target): bool
    {
        return $this->sharesOrganization($user, $target, 'users.disable');
    }

    /**
     * La permission est évaluée dans chaque organisation que les deux comptes
     * ont en commun : il suffit d'une pour autoriser l'action.
     */
    private function sharesOrganization(User $user, User $target, string $permission): bool
    {
        $organizationIds = OrganizationUser::where('user_id', $target->id)->pluck('organization_id');

        foreach ($organizationIds as $organizationId) {
            if ($this->hasPermission($user, $organizationId, $permission)) {
                return true;
            }
        }

        return false;
    }
}
