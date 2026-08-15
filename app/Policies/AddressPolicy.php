<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Addresses\Models\Address;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\OrganizationUser;
use Illuminate\Auth\Access\Response;

/**
 * Une adresse d'une autre organisation est **introuvable**, pas interdite —
 * corrigé en Phase 10. Voir `BaseOrganizationPolicy::notFound()`.
 *
 * L'adresse ne porte pas `organization_id` : son périmètre vient de ses
 * `EntityAddress`. Une adresse **sans aucun rattachement** est traitée comme
 * hors périmètre — elle n'appartient à personne, donc à aucune organisation
 * active. C'était déjà le comportement de `view` avant la Phase 10 ; il est
 * désormais appliqué aussi à `update` et `delete`, qui s'en dispensaient.
 */
class AddressPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'addresses.view');
    }

    public function view(User $user, Address $address): Response|bool
    {
        return $this->scoped($user, $address, 'addresses.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'addresses.create');
    }

    public function update(User $user, Address $address): Response|bool
    {
        return $this->scoped($user, $address, 'addresses.update');
    }

    public function delete(User $user, Address $address): Response|bool
    {
        return $this->scoped($user, $address, 'addresses.delete');
    }

    private function scoped(User $user, Address $address, string $permission): Response|bool
    {
        if (! $this->addressBelongsToOrganization($address, $user)) {
            return $this->notFound();
        }

        return $this->hasPermission($user, $this->organizationIdForAddress($address), $permission);
    }

    private function addressBelongsToOrganization(Address $address, User $user): bool
    {
        $organizationIds = OrganizationUser::where('user_id', $user->id)
            ->pluck('organization_id');

        return $address->entityAddresses()
            ->whereIn('organization_id', $organizationIds)
            ->exists();
    }

    private function organizationIdForAddress(Address $address): ?string
    {
        return $address->entityAddresses()
            ->value('organization_id');
    }
}
