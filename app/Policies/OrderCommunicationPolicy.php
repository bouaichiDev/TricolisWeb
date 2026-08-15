<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Identity\Models\User;

/**
 * `queue`, `cancel` et `retry` ont leur propre permission : elles déclenchent
 * ou interrompent un envoi vers un tiers, ce qui n'est pas une modification de
 * contenu. Pouvoir corriger un brouillon ne doit pas suffire à l'expédier.
 */
class OrderCommunicationPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'order_communications.view');
    }

    public function view(User $user, OrderCommunication $communication): bool
    {
        return $this->hasPermission($user, $communication->organization_id, 'order_communications.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'order_communications.create');
    }

    public function update(User $user, OrderCommunication $communication): bool
    {
        return $this->hasPermission($user, $communication->organization_id, 'order_communications.update');
    }

    public function delete(User $user, OrderCommunication $communication): bool
    {
        return $this->hasPermission($user, $communication->organization_id, 'order_communications.delete');
    }

    public function queue(User $user, OrderCommunication $communication): bool
    {
        return $this->hasPermission($user, $communication->organization_id, 'order_communications.queue');
    }

    public function cancel(User $user, OrderCommunication $communication): bool
    {
        return $this->hasPermission($user, $communication->organization_id, 'order_communications.cancel');
    }

    public function retry(User $user, OrderCommunication $communication): bool
    {
        return $this->hasPermission($user, $communication->organization_id, 'order_communications.retry');
    }
}
