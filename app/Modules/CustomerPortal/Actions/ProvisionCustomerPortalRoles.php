<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Support\CustomerPortalRoles;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Shared\Enums\RoleScope;

/**
 * Pose les rôles du portail client d'une organisation de transporteur.
 *
 * Idempotent, et **conservateur** : un rôle existant garde ses permissions.
 * Le transporteur doit pouvoir ajuster ce qu'il autorise à ses clients sans
 * qu'un nouveau semis le défasse. Seul un rôle encore vide reçoit le jeu par
 * défaut — c'est le cas d'un rôle créé avant que les permissions existent.
 *
 * Les rôles internes ne sont jamais touchés : la recherche se fait par
 * `(organization_id, code)` sur des codes `CUSTOMER_*` et la portée est
 * imposée à `customer`.
 */
final readonly class ProvisionCustomerPortalRoles
{
    /**
     * @param  array<int, string>  $newPermissions  codes apparus avec ce semis : ils
     *                                              rejoignent les rôles par défaut qui les prévoient.
     */
    public function execute(string $organizationId, array $newPermissions = []): void
    {
        foreach (CustomerPortalRoles::defaults() as $code => $definition) {
            $role = Role::firstOrCreate(
                ['organization_id' => $organizationId, 'code' => $code],
                [
                    'name' => $definition['name'],
                    'scope' => RoleScope::CUSTOMER->value,
                    'is_system' => true,
                    'status' => 'active',
                ],
            );

            if ($role->scope !== RoleScope::CUSTOMER->value) {
                // Un rôle interne porterait déjà ce code : on ne le détourne pas.
                continue;
            }

            if ($role->wasRecentlyCreated || ! $role->permissions()->exists()) {
                $this->grant($role, $definition['permissions']);

                continue;
            }

            // Une permission nouvelle n'a jamais pu être retirée par le
            // transporteur : l'accorder ne défait aucun de ses réglages.
            $this->grant($role, array_values(array_intersect($definition['permissions'], $newPermissions)));
        }
    }

    /**
     * @param  array<int, string>  $codes
     */
    private function grant(Role $role, array $codes): void
    {
        foreach (Permission::whereIn('code', $codes)->pluck('id') as $permissionId) {
            RolePermission::firstOrCreate(['role_id' => $role->id, 'permission_id' => $permissionId]);
        }
    }
}
