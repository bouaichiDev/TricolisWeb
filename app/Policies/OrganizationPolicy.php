<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PlatformAccess;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;

/**
 * Deux niveaux d'autorité sur les organisations.
 *
 * La plateforme les crée, les liste toutes et les supprime. Un organisme ne fait
 * que consulter et modifier **la sienne**.
 *
 * Avant cette correction, `viewAny()` et `create()` renvoyaient `true` sans
 * condition : tout compte authentifié pouvait créer une organisation, et le rôle
 * `admin` semé portait `organizations.create`. Un administrateur d'organisme
 * pouvait donc se doter d'un périmètre qui ne lui revenait pas.
 */
class OrganizationPolicy extends BaseOrganizationPolicy
{
    public function __construct(private readonly PlatformAccess $platform) {}

    /**
     * Lister les organisations.
     *
     * Autorisé pour tout membre : le contrôleur borne le résultat à ses propres
     * rattachements, sauf pour un administrateur plateforme qui les voit toutes.
     * Refuser ici priverait un utilisateur de la liste servant à choisir son
     * organisation active.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->platform->isPlatformAdmin($user)
            || $this->hasOrganizationAccess($user, $organization->id);
    }

    /**
     * Créer une organisation : plateforme uniquement.
     *
     * La permission `organizations.create` est réservée à la plateforme et ne
     * peut donc être obtenue par délégation locale. La double vérification —
     * portée **et** permission — évite qu'un rôle plateforme incomplet suffise.
     */
    public function create(User $user): bool
    {
        return $this->platform->hasPlatformPermission($user, 'organizations.create');
    }

    /**
     * Modifier une organisation.
     *
     * Un administrateur plateforme modifie n'importe laquelle. Un membre ne
     * modifie que la sienne, et seulement avec la permission ou la qualité de
     * propriétaire.
     */
    public function update(User $user, Organization $organization): bool
    {
        if ($this->platform->isPlatformAdmin($user)) {
            return true;
        }

        if (! $this->hasOrganizationAccess($user, $organization->id)) {
            return false;
        }

        return $this->hasPermission($user, $organization->id, 'organizations.update')
            || $this->isOwner($user, $organization->id);
    }

    /**
     * Supprimer une organisation : plateforme uniquement.
     *
     * La qualité de propriétaire ne suffit plus. Elle suffisait auparavant, ce
     * qui permettait à un organisme de se supprimer lui-même — avec ses
     * commandes, ses factures et son journal d'audit.
     */
    public function delete(User $user, Organization $organization): bool
    {
        return $this->platform->hasPlatformPermission($user, 'organizations.delete');
    }

    private function isOwner(User $user, string $organizationId): bool
    {
        return OrganizationUser::where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->where('is_owner', true)
            ->exists();
    }
}
