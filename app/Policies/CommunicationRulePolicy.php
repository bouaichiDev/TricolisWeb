<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Communications\Models\CommunicationRule;
use App\Modules\Identity\Models\User;

class CommunicationRulePolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'communication_rules.view');
    }

    public function view(User $user, CommunicationRule $rule): bool
    {
        return $this->hasPermission($user, $rule->organization_id, 'communication_rules.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'communication_rules.create');
    }

    public function update(User $user, CommunicationRule $rule): bool
    {
        return $this->hasPermission($user, $rule->organization_id, 'communication_rules.update');
    }

    public function delete(User $user, CommunicationRule $rule): bool
    {
        return $this->hasPermission($user, $rule->organization_id, 'communication_rules.delete');
    }
}
