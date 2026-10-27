<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Shared\Enums\RoleScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Quels rôles un membre du portail peut-il attribuer ?
 *
 * 1. **les rôles du portail de ce transporteur**, et eux seuls : un
 *    identifiant de rôle interne, ou d'un autre transporteur, est inconnu ici ;
 * 2. **le plafond de délégation** : nul n'attribue un rôle portant une
 *    permission qu'il ne détient pas lui-même. C'est ce qui empêche un
 *    gestionnaire de commandes promu « gestion des utilisateurs » de se
 *    fabriquer un administrateur.
 */
final class CustomerRoleGuard
{
    /**
     * @return Collection<int, Role>
     */
    public static function rolesOf(Customer $customer): Collection
    {
        return Role::with('permissions:id,code')
            ->where('organization_id', $customer->organization_id)
            ->where('scope', RoleScope::CUSTOMER->value)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<int, string>  $roleIds
     * @param  array<int, string>  $heldPermissions
     * @return Collection<int, Role>
     *
     * @throws ValidationException
     */
    public static function resolve(Customer $customer, array $roleIds, array $heldPermissions, string $field = 'roleIds'): Collection
    {
        $roleIds = array_values(array_unique($roleIds));
        $roles = self::rolesOf($customer)->whereIn('id', $roleIds)->values();

        if ($roles->count() !== count($roleIds)) {
            throw ValidationException::withMessages([$field => ['Un des rôles demandés n’existe pas pour ce portail.']]);
        }

        foreach ($roles as $role) {
            $beyond = array_diff(CustomerPermissions::codesOfRole($role), $heldPermissions);

            if ($beyond !== []) {
                throw ValidationException::withMessages([
                    $field => ["Vous ne pouvez pas attribuer le rôle « {$role->name} » : il porte des droits que vous n’avez pas."],
                ]);
            }
        }

        return $roles;
    }
}
