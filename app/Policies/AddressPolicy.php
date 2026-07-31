<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Addresses\Models\Address;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\OrganizationUser;

class AddressPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'addresses.view');
    }

    public function view(User $user, Address $address): bool
    {
        $organizationId = $this->organizationIdForAddress($address);

        return $this->addressBelongsToOrganization($address, $user) && $this->hasPermission($user, $organizationId, 'addresses.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'addresses.create');
    }

    public function update(User $user, Address $address): bool
    {
        return $this->hasPermission($user, $this->organizationIdForAddress($address), 'addresses.update');
    }

    public function delete(User $user, Address $address): bool
    {
        return $this->hasPermission($user, $this->organizationIdForAddress($address), 'addresses.delete');
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
