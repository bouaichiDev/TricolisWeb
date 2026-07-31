<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Subscription;

class SubscriptionPolicy extends BaseOrganizationPolicy
{
    public function view(User $user, Subscription $subscription): bool
    {
        return $this->hasPermission($user, $subscription->organization_id, 'subscriptions.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'subscriptions.create');
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $this->hasPermission($user, $subscription->organization_id, 'subscriptions.update');
    }

    public function delete(User $user, Subscription $subscription): bool
    {
        return $this->hasPermission($user, $subscription->organization_id, 'subscriptions.delete');
    }

    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'subscriptions.view');
    }
}
