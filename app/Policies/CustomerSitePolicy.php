<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerSite;
use App\Modules\Identity\Models\User;

class CustomerSitePolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, Customer $customer): bool
    {
        return $this->hasPermission($user, $customer->organization_id, 'customer_sites.view');
    }

    public function view(User $user, CustomerSite $site): bool
    {
        return $this->hasPermission($user, $site->customer->organization_id, 'customer_sites.view');
    }

    public function create(User $user, Customer $customer): bool
    {
        return $this->hasPermission($user, $customer->organization_id, 'customer_sites.create');
    }

    public function update(User $user, CustomerSite $site): bool
    {
        return $this->hasPermission($user, $site->customer->organization_id, 'customer_sites.update');
    }

    public function delete(User $user, CustomerSite $site): bool
    {
        return $this->hasPermission($user, $site->customer->organization_id, 'customer_sites.delete');
    }
}
