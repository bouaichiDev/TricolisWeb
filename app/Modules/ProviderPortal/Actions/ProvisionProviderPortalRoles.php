<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Actions;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\ProviderPortal\Support\ProviderPortalRoles;
use App\Shared\Enums\RoleScope;

/**
 * Pose les rôles du portail fournisseur d'une organisation de transporteur.
 *
 * Idempotent et **conservateur**, comme `ProvisionCustomerPortalRoles` : un
 * rôle existant garde ses permissions ; seules les permissions apparues avec
 * ce semis rejoignent les rôles par défaut qui les prévoient.
 *
 * Un rôle interne ou client qui porterait déjà un code `PROVIDER_*` n'est
 * jamais détourné : la portée doit être `provider`.
 */
final readonly class ProvisionProviderPortalRoles
{
    /**
     * @param  array<int, string>  $newPermissions  codes apparus avec ce semis
     */
    public function execute(string $organizationId, array $newPermissions = []): void
    {
        foreach (ProviderPortalRoles::defaults() as $code => $definition) {
            $role = Role::firstOrCreate(
                ['organization_id' => $organizationId, 'code' => $code],
                [
                    'name' => $definition['name'],
                    'scope' => RoleScope::PROVIDER->value,
                    'is_system' => true,
                    'status' => 'active',
                ],
            );

            if ($role->scope !== RoleScope::PROVIDER->value) {
                continue;
            }

            if ($role->wasRecentlyCreated || ! $role->permissions()->exists()) {
                $this->grant($role, $definition['permissions']);

                continue;
            }

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
