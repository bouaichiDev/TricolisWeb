<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;

class CustomerPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->hasPermission($user, $customer->organization_id, 'customers.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->hasPermission($user, $customer->organization_id, 'customers.update');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->hasPermission($user, $customer->organization_id, 'customers.delete');
    }
}
