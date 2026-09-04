<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Types\Models\TypeItem;
use Illuminate\Auth\Access\Response;

/** Les valeurs suivent les droits de leur source : un seul module `types.*`. */
class TypeItemPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'types.view');
    }

    public function view(User $user, TypeItem $item): Response|bool
    {
        return $this->scoped($user, $item, 'types.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'types.create');
    }

    public function update(User $user, TypeItem $item): Response|bool
    {
        return $this->scoped($user, $item, 'types.update');
    }

    public function delete(User $user, TypeItem $item): Response|bool
    {
        return $this->scoped($user, $item, 'types.delete');
    }

    private function scoped(User $user, TypeItem $item, string $permission): Response|bool
    {
        if (! $this->seesOrganization($user, $item->organization_id)) {
            return $this->notFound();
        }

        return $this->hasPermission($user, $item->organization_id, $permission);
    }
}
