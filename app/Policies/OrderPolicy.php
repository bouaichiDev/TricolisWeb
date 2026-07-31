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

    public function duplicate(User $user, Order $order): bool
    {
        return $this->hasPermission($user, $order->organization_id, 'orders.duplicate');
    }

    public function changeStatus(User $user, Order $order): bool
    {
        return $this->hasPermission($user, $order->organization_id, 'orders.change_status');
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->hasPermission($user, $order->organization_id, 'orders.cancel');
    }

    /**
     * Les lignes, colis et services héritent du périmètre de leur commande.
     */
    public function manageLines(User $user, Order $order, string $action): bool
    {
        return $this->hasPermission($user, $order->organization_id, "order_lines.$action");
    }

    public function managePackages(User $user, Order $order, string $action): bool
    {
        return $this->hasPermission($user, $order->organization_id, "packages.$action");
    }

    public function manageServices(User $user, Order $order, string $action): bool
    {
        return $this->hasPermission($user, $order->organization_id, "order_services.$action");
    }
}
