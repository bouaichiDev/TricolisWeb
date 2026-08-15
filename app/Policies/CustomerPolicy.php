<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * `block` est distincte d'`update` — rattachée en Phase 10.
 *
 * La permission `customers.block` était déclarée au seeder depuis la Phase 2
 * mais n'était contrôlée nulle part : bloquer un client passait par
 * `customers.update`, au même titre que corriger son téléphone. Or bloquer un
 * client interrompt ses commandes ; c'est un geste d'exploitation, pas une
 * correction de fiche. Même raisonnement que pour `queue`, `cancel` et `retry`
 * en Phase 9.
 */
class CustomerPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'customers.view');
    }

    public function view(User $user, Customer $customer): Response|bool
    {
        return $this->scoped($user, $customer, 'customers.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'customers.create');
    }

    public function update(User $user, Customer $customer): Response|bool
    {
        return $this->scoped($user, $customer, 'customers.update');
    }

    public function delete(User $user, Customer $customer): Response|bool
    {
        return $this->scoped($user, $customer, 'customers.delete');
    }

    public function block(User $user, Customer $customer): Response|bool
    {
        return $this->scoped($user, $customer, 'customers.block');
    }

    /**
     * Hors organisation → 404 ; dans l'organisation mais sans droit → 403.
     */
    private function scoped(User $user, Customer $customer, string $permission): Response|bool
    {
        if (! $this->seesOrganization($user, $customer->organization_id)) {
            return $this->notFound();
        }

        return $this->hasPermission($user, $customer->organization_id, $permission);
    }
}
