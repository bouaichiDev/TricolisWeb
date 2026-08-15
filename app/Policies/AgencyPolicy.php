<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Une agence d'une autre organisation est **introuvable**, pas interdite —
 * corrigé en Phase 10. Voir `BaseOrganizationPolicy::notFound()`.
 */
class AgencyPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'agencies.view');
    }

    public function view(User $user, Agency $agency): Response|bool
    {
        return $this->scoped($user, $agency, 'agencies.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'agencies.create');
    }

    public function update(User $user, Agency $agency): Response|bool
    {
        return $this->scoped($user, $agency, 'agencies.update');
    }

    public function delete(User $user, Agency $agency): Response|bool
    {
        return $this->scoped($user, $agency, 'agencies.delete');
    }

    private function scoped(User $user, Agency $agency, string $permission): Response|bool
    {
        if (! $this->seesOrganization($user, $agency->organization_id)) {
            return $this->notFound();
        }

        return $this->hasPermission($user, $agency->organization_id, $permission);
    }
}
