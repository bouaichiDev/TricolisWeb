<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Enums\RoleScope;
use Illuminate\Support\Collection;

/**
 * Frontière entre l'administration de la plateforme et celle d'un organisme.
 *
 * Un seul fait détermine l'autorité plateforme : l'utilisateur détient-il un
 * rôle de portée `PLATFORM` ? Ni le code du rôle, ni son nom, ni le drapeau
 * `is_owner` d'une organisation n'entrent en compte — sans quoi il suffirait de
 * nommer un rôle « SuperAdmin » pour en obtenir les pouvoirs.
 *
 * Le service porte aussi le **plafond de délégation** : nul ne peut accorder
 * plus de droits qu'il n'en détient lui-même. C'est ce qui empêche un
 * administrateur d'organisme de fabriquer un rôle plus puissant que le sien
 * puis de se l'attribuer.
 */
class PlatformAccess
{
    /**
     * Permissions réservées à la plateforme.
     *
     * Elles ne sont jamais proposées ni acceptées dans un rôle d'organisation :
     * créer ou supprimer une organisation dépasse par nature le périmètre d'un
     * organisme. La liste ne contient que des codes existants du référentiel ;
     * elle n'en invente aucun.
     */
    public const array PLATFORM_PERMISSIONS = [
        'organizations.create',
        'organizations.delete',
        // Le referentiel des statuts decrit le cycle de vie du domaine, pas la
        // preference d'un organisme : le laisser deleguer localement rendrait
        // les commandes de deux organismes incomparables.
        'statuses.create',
        'statuses.update',
        'statuses.delete',
    ];

    public function isPlatformAdmin(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->platformRoles($user)->isNotEmpty();
    }

    /**
     * L'utilisateur détient-il cette permission au niveau plateforme ?
     *
     * La vérification ne passe pas par `hasPermission()` : celle-ci est bornée à
     * une organisation, alors qu'un rôle plateforme n'en a aucune.
     */
    public function hasPlatformPermission(?User $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->platformRoles($user)
            ->contains(fn (Role $role) => $role->permissions->contains('code', $permission));
    }

    /**
     * Codes des permissions que cet utilisateur peut accorder à un rôle.
     *
     * Intersection de trois ensembles, dans cet ordre :
     *
     * 1. le référentiel, moins les permissions réservées à la plateforme ;
     * 2. ce que l'utilisateur détient lui-même dans l'organisation active ;
     * 3. tout, s'il est propriétaire — le propriétaire détient déjà l'intégralité
     *    des droits de son organisation, mais jamais ceux de la plateforme.
     *
     * Un administrateur plateforme échappe au retrait du point 1 : il peut
     * déléguer ce qu'il détient, y compris les permissions plateforme.
     *
     * @return array<int, string>
     */
    public function delegablePermissionCodes(?User $user, ?string $organizationId): array
    {
        if ($user === null) {
            return [];
        }

        $all = Permission::pluck('code')->all();

        if ($this->isPlatformAdmin($user)) {
            return $all;
        }

        $organizational = array_values(array_diff($all, self::PLATFORM_PERMISSIONS));

        $membership = $organizationId === null ? null : OrganizationUser::where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->with('roles.permissions')
            ->first();

        if ($membership === null) {
            return [];
        }

        if ($membership->is_owner) {
            return $organizational;
        }

        $held = $membership->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('code'))
            ->unique()
            ->all();

        return array_values(array_intersect($organizational, $held));
    }

    /**
     * Rôles attribuables par cet utilisateur dans l'organisation active.
     *
     * Un rôle système est exclu : il est livré avec l'application et l'attribuer
     * reviendrait à contourner le plafond de délégation, puisqu'il porte des
     * permissions que l'attribuant ne détient pas nécessairement.
     *
     * @return Collection<int, Role>
     */
    public function assignableRoles(?User $user, ?string $organizationId): Collection
    {
        if ($user === null || $organizationId === null) {
            return collect();
        }

        $query = Role::where('organization_id', $organizationId)
            ->where('scope', RoleScope::ORGANIZATION->value);

        if (! $this->isPlatformAdmin($user)) {
            $query->where('is_system', false);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, Role>
     */
    private function platformRoles(User $user): Collection
    {
        return Role::where('scope', RoleScope::PLATFORM->value)
            ->whereHas('organizationUsers', fn ($query) => $query->where('user_id', $user->id))
            ->with('permissions')
            ->get();
    }
}
