<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Stock\Models\StockItem;

/**
 * L'article n'a pas d'organisation propre : sa permission est évaluée dans
 * celle de son client.
 */
class StockItemPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'stock_items.view');
    }

    public function view(User $user, StockItem $item): bool
    {
        return $this->hasPermission($user, $this->organizationOf($item), 'stock_items.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'stock_items.create');
    }

    public function update(User $user, StockItem $item): bool
    {
        return $this->hasPermission($user, $this->organizationOf($item), 'stock_items.update');
    }

    public function delete(User $user, StockItem $item): bool
    {
        return $this->hasPermission($user, $this->organizationOf($item), 'stock_items.delete');
    }

    private function organizationOf(StockItem $item): ?string
    {
        return $item->customer?->organization_id;
    }
}
