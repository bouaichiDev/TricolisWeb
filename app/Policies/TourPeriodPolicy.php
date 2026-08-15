<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Tours\Models\TourPeriod;

class TourPeriodPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'tour_periods.view');
    }

    public function view(User $user, TourPeriod $period): bool
    {
        return $this->hasPermission($user, $this->organizationOf($period), 'tour_periods.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'tour_periods.create');
    }

    public function update(User $user, TourPeriod $period): bool
    {
        return $this->hasPermission($user, $this->organizationOf($period), 'tour_periods.update');
    }

    public function delete(User $user, TourPeriod $period): bool
    {
        return $this->hasPermission($user, $this->organizationOf($period), 'tour_periods.delete');
    }

    public function reorder(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'tour_periods.reorder');
    }

    private function organizationOf(TourPeriod $period): ?string
    {
        return $period->tour?->organization_id;
    }
}
