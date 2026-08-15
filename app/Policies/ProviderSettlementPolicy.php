<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;

class ProviderSettlementPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'provider_settlements.view');
    }

    public function view(User $user, ProviderSettlement $settlement): bool
    {
        return $this->hasPermission($user, $settlement->organization_id, 'provider_settlements.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'provider_settlements.create');
    }

    public function update(User $user, ProviderSettlement $settlement): bool
    {
        return $this->hasPermission($user, $settlement->organization_id, 'provider_settlements.update');
    }

    public function delete(User $user, ProviderSettlement $settlement): bool
    {
        return $this->hasPermission($user, $settlement->organization_id, 'provider_settlements.delete');
    }
}
