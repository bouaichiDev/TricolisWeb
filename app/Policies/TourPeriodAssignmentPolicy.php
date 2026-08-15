<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Tours\Models\TourPeriodAssignment;

class TourPeriodAssignmentPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'tour_period_assignments.view');
    }

    public function view(User $user, TourPeriodAssignment $assignment): bool
    {
        return $this->hasPermission($user, $this->organizationOf($assignment), 'tour_period_assignments.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'tour_period_assignments.create');
    }

    public function update(User $user, TourPeriodAssignment $assignment): bool
    {
        return $this->hasPermission($user, $this->organizationOf($assignment), 'tour_period_assignments.update');
    }

    public function delete(User $user, TourPeriodAssignment $assignment): bool
    {
        return $this->hasPermission($user, $this->organizationOf($assignment), 'tour_period_assignments.delete');
    }

    private function organizationOf(TourPeriodAssignment $assignment): ?string
    {
        return $assignment->tourPeriod?->tour?->organization_id;
    }
}
