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

    /**
     * Régler le **menu** d'un rôle, ce qui n'est pas le modifier.
     *
     * `update` protège le jeu de permissions : un rôle système le porte tout
     * entier, et le laisser modifier ouvrirait une voie d'élévation. Le menu ne
     * porte rien de tel — il range des écrans, il n'en ouvre aucun. Interdire de
     * le régler sur le rôle `admin` privait l'administrateur du seul menu qu'il
     * voit lui-même, pour une raison qui ne le concernait pas.
     *
     * Les deux autres conditions demeurent : le rôle doit appartenir à
     * l'organisation de l'appelant, et rester de portée organisation — un rôle
     * plateforme n'est pas le sien.
     */
    public function updateMenu(User $user, Role $role): Response|bool
    {
        if ($this->platform->isPlatformAdmin($user)) {
            return true;
        }

        if (! $this->seesOrganization($user, $role->organization_id)) {
            return $this->notFound();
        }

        if (RoleScope::tryFromValue($role->scope) !== RoleScope::ORGANIZATION) {
            return false;
        }

        return $this->hasPermission($user, $role->organization_id, 'roles.update');
    }

    /**
     * Régler le **tableau de bord** d'un rôle.
     *
     * Même raisonnement que `updateMenu`, et une différence : la permission.
     * Le menu est réglé par qui règle le rôle, donc `roles.update` ; le tableau
     * de bord a la sienne, `dashboard.configure`, parce que composer des cartes
     * est un travail métier qu'on veut pouvoir confier sans donner du même
     * geste le droit de modifier les permissions d'un rôle.
     *
     * Ce que cette autorisation ne fait **jamais**, c'est accorder : un widget
     * dont le rôle n'a pas la permission peut être activé — l'interface le
     * refuse, et le serveur le refuserait aussi — mais il ne s'affichera pas
     * pour autant. L'intersection avec les permissions effectives a lieu à
     * chaque chargement du tableau de bord, et elle n'est pas contournable
     * depuis cet écran.
     *
     * Le rôle système y échappe comme pour le menu : `admin` porte toutes les
     * permissions, et lui interdire de régler son propre tableau de bord aurait
     * privé l'administrateur du seul qu'il voit. Restent les deux conditions
     * qui protègent vraiment : le rôle est de son organisation, et de portée
     * organisation.
     */
    public function configureDashboard(User $user, Role $role): Response|bool
    {
        if ($this->platform->isPlatformAdmin($user)) {
            return true;
        }

        if (! $this->seesOrganization($user, $role->organization_id)) {
            return $this->notFound();
        }

        if (RoleScope::tryFromValue($role->scope) !== RoleScope::ORGANIZATION) {
            return false;
        }

        return $this->hasPermission($user, $role->organization_id, 'dashboard.configure');
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
