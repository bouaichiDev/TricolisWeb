<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Exports\Models\ExportJob;
use App\Modules\Identity\Models\User;

/**
 * Ni `update`, ni `delete` : un export est une exécution historique, les routes
 * n'existent pas. `retry` est la seule écriture, et elle a sa permission.
 */
class ExportJobPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'export_jobs.view');
    }

    public function view(User $user, ExportJob $job): bool
    {
        return $this->hasPermission($user, $this->organizationOf($job), 'export_jobs.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'export_jobs.create');
    }

    public function retry(User $user, ExportJob $job): bool
    {
        return $this->hasPermission($user, $this->organizationOf($job), 'export_jobs.retry');
    }

    private function organizationOf(ExportJob $job): ?string
    {
        return $job->customer?->organization_id;
    }
}
