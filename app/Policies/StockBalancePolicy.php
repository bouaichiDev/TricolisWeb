<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Stock\Models\StockBalance;

/**
 * Lecture seule.
 *
 * Ni `create`, ni `update`, ni `delete` : le §14 interdit un CRUD public sur
 * les soldes. Ils ne bougent que par les mouvements et les réservations.
 */
class StockBalancePolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'stock_balances.view');
    }

    public function view(User $user, StockBalance $balance): bool
    {
        return $this->hasPermission(
            $user,
            $balance->stockItem?->customer?->organization_id,
            'stock_balances.view',
        );
    }
}
