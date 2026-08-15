<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Stock\Models\StockLocation;

/**
 * L'emplacement tient son périmètre de son dépôt, qui le tient de son agence.
 */
class StockLocationPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'stock_locations.view');
    }

    public function view(User $user, StockLocation $location): bool
    {
        return $this->hasPermission($user, $this->organizationOf($location), 'stock_locations.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'stock_locations.create');
    }

    public function update(User $user, StockLocation $location): bool
    {
        return $this->hasPermission($user, $this->organizationOf($location), 'stock_locations.update');
    }

    public function delete(User $user, StockLocation $location): bool
    {
        return $this->hasPermission($user, $this->organizationOf($location), 'stock_locations.delete');
    }

    private function organizationOf(StockLocation $location): ?string
    {
        return $location->depot?->agency?->organization_id;
    }
}
