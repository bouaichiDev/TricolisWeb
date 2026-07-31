<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Contacts\Models\EntityContact;
use App\Modules\Identity\Models\User;

class EntityContactPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'contacts.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'contacts.create');
    }

    public function update(User $user, EntityContact $entityContact): bool
    {
        return $this->hasPermission($user, $entityContact->organization_id, 'contacts.update');
    }

    public function delete(User $user, EntityContact $entityContact): bool
    {
        return $this->hasPermission($user, $entityContact->organization_id, 'contacts.delete');
    }
}
