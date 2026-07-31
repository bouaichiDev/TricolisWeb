<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;

class AuditLogPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'audit.view');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->hasPermission($user, $auditLog->organization_id, 'audit.view');
    }
}
