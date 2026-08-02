<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;

class OrderPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        return $this->hasPermission($user, $order->organization_id, 'orders.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'orders.create');
    }

    public function update(User $user, Order $order): bool
    {
        return $this->hasPermission($user, $order->organization_id, 'orders.update');
    }

    public function delete(User $user, Order $order): bool
    {
        return $this->hasPermission($user, $order->organization_id, 'orders.delete');
    }
}
