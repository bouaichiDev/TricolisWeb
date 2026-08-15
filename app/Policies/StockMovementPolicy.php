<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Stock\Models\StockMovement;

/**
 * Ni `update`, ni `delete` : un mouvement est historique, les routes n'existent
 * pas.
 */
class StockMovementPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'stock_movements.view');
    }

    public function view(User $user, StockMovement $movement): bool
    {
        return $this->hasPermission(
            $user,
            $movement->stockItem?->customer?->organization_id,
            'stock_movements.view',
        );
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'stock_movements.create');
    }
}
