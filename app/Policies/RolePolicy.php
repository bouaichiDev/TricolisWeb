<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PlatformAccess;
use App\Shared\Enums\RoleScope;
use Illuminate\Auth\Access\Response;

/**
 * Autorité sur les rôles.
 *
 * Un administrateur d'organisme ne touche qu'aux rôles qui remplissent les trois
 * conditions à la fois :
 *
 * ```
 * role.organization_id == organisation active
 * role.scope           == organization
 * role.is_system       == false
 * ```
 *
 * Chacune ferme une voie d'élévation distincte : la première empêche d'agir sur
 * l'organisation d'un tiers, la deuxième d'atteindre un rôle plateforme, la
 * troisième de modifier un rôle livré avec l'application — dont le rôle `admin`,
 * qui porte l'ensemble des permissions de l'organisation.
 *
 * Un rôle plateforme n'a pas d'organisation : il est donc invisible et
 * intouchable depuis une administration locale, sans traitement particulier.
 */
class RolePolicy extends BaseOrganizationPolicy
{
    public function __construct(private readonly PlatformAccess $platform) {}

    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->platform->isPlatformAdmin($user)
            || $this->hasPermission($user, $organizationId, 'roles.view');
    }

    public function view(User $user, Role $role): Response|bool
    {
        if ($this->platform->isPlatformAdmin($user)) {
            return true;
        }

        if (! $this->seesOrganization($user, $role->organization_id)) {
            return $this->notFound();
        }

        return $this->hasPermission($user, $role->organization_id, 'roles.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->platform->isPlatformAdmin($user)
            || $this->hasPermission($user, $organizationId, 'roles.create');
    }

    public function update(User $user, Role $role): Response|bool
    {
        return $this->mutate($user, $role, 'roles.update');
    }

    public function delete(User $user, Role $role): Response|bool
    {
        return $this->mutate($user, $role, 'roles.delete');
    }

    /**
     * Un rôle peut-il être attribué à un membre par cet utilisateur ?
     *
     * Distinct de `update` : attribuer ne modifie pas le rôle, mais transmet ses
     * permissions. Un rôle système ou plateforme les transmettrait sans que
     * l'attribuant les détienne — le plafond de délégation serait contourné.
     */
    public function assign(User $user, Role $role, string $organizationId): Response|bool
    {
        if ($this->platform->isPlatformAdmin($user)) {
            return true;
        }

        if ($role->organization_id !== $organizationId) {
            return $this->notFound();
        }

        if ($role->is_system || RoleScope::tryFromValue($role->scope) !== RoleScope::ORGANIZATION) {
            return false;
        }

        return $this->hasPermission($user, $organizationId, 'users.assign_roles');
    }

    /**
     * Socle commun à la modification et à la suppression.
     *
     * L'ordre des refus est délibéré : l'appartenance d'abord — un rôle d'une
     * autre organisation se présente comme absent, pas comme interdit, sinon la
     * différence entre les deux réponses confirmerait son existence.
     */
    private function mutate(User $user, Role $role, string $permission): Response|bool
    {
        if ($this->platform->isPlatformAdmin($user)) {
            return true;
        }

        if (! $this->seesOrganization($user, $role->organization_id)) {
            return $this->notFound();
        }

        if ($role->is_system) {
            return false;
        }

        if (RoleScope::tryFromValue($role->scope) !== RoleScope::ORGANIZATION) {
            return false;
        }

        return $this->hasPermission($user, $role->organization_id, $permission);
    }
}
