<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\ProviderSettlements\Models\ProviderSettlementLine;

class ProviderSettlementLinePolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'provider_settlement_lines.view');
    }

    public function view(User $user, ProviderSettlementLine $line): bool
    {
        return $this->hasPermission($user, $this->organizationOf($line), 'provider_settlement_lines.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'provider_settlement_lines.create');
    }

    public function update(User $user, ProviderSettlementLine $line): bool
    {
        return $this->hasPermission($user, $this->organizationOf($line), 'provider_settlement_lines.update');
    }

    public function delete(User $user, ProviderSettlementLine $line): bool
    {
        return $this->hasPermission($user, $this->organizationOf($line), 'provider_settlement_lines.delete');
    }

    private function organizationOf(ProviderSettlementLine $line): ?string
    {
        return $line->settlement?->organization_id;
    }
}
