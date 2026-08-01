<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Tours\Models\TourStopService;

class TourStopServicePolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'tour_stop_services.view');
    }

    public function view(User $user, TourStopService $service): bool
    {
        return $this->hasPermission($user, $this->organizationOf($service), 'tour_stop_services.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'tour_stop_services.create');
    }

    public function update(User $user, TourStopService $service): bool
    {
        return $this->hasPermission($user, $this->organizationOf($service), 'tour_stop_services.update');
    }

    public function delete(User $user, TourStopService $service): bool
    {
        return $this->hasPermission($user, $this->organizationOf($service), 'tour_stop_services.delete');
    }

    public function reorder(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'tour_stop_services.reorder');
    }

    private function organizationOf(TourStopService $service): ?string
    {
        return $service->tourStop?->tour?->organization_id;
    }
}
