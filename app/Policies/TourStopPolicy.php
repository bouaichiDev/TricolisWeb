<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Tours\Models\TourStop;

/**
 * L'arrêt tient son périmètre de sa tournée : c'est elle qui porte
 * l'organisation.
 */
class TourStopPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'tour_stops.view');
    }

    public function view(User $user, TourStop $stop): bool
    {
        return $this->hasPermission($user, $this->organizationOf($stop), 'tour_stops.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'tour_stops.create');
    }

    public function update(User $user, TourStop $stop): bool
    {
        return $this->hasPermission($user, $this->organizationOf($stop), 'tour_stops.update');
    }

    public function delete(User $user, TourStop $stop): bool
    {
        return $this->hasPermission($user, $this->organizationOf($stop), 'tour_stops.delete');
    }

    public function reorder(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'tour_stops.reorder');
    }

    private function organizationOf(TourStop $stop): ?string
    {
        return $stop->tour?->organization_id;
    }
}
