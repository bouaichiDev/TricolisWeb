<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Tours\Models\Tour;

/**
 * La tournée porte son organisation : la permission s'évalue dessus.
 */
class TourPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'tours.view');
    }

    public function view(User $user, Tour $tour): bool
    {
        return $this->hasPermission($user, $tour->organization_id, 'tours.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'tours.create');
    }

    public function update(User $user, Tour $tour): bool
    {
        return $this->hasPermission($user, $tour->organization_id, 'tours.update');
    }

    public function delete(User $user, Tour $tour): bool
    {
        return $this->hasPermission($user, $tour->organization_id, 'tours.delete');
    }
}
