<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;

/**
 * Un catalogue est visible dans l'organisation de son client.
 */
class CustomerCatalogPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, Customer $customer): bool
    {
        return $this->hasPermission($user, $customer->organization_id, 'catalogs.view');
    }

    public function view(User $user, CustomerCatalog $catalog): bool
    {
        return $this->hasPermission($user, $catalog->customer->organization_id, 'catalogs.view');
    }

    public function create(User $user, Customer $customer): bool
    {
        return $this->hasPermission($user, $customer->organization_id, 'catalogs.create');
    }

    public function update(User $user, CustomerCatalog $catalog): bool
    {
        return $this->hasPermission($user, $catalog->customer->organization_id, 'catalogs.update');
    }

    public function delete(User $user, CustomerCatalog $catalog): bool
    {
        return $this->hasPermission($user, $catalog->customer->organization_id, 'catalogs.delete');
    }
}
