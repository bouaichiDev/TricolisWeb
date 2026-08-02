<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use Illuminate\Database\Seeder;

/**
 * Crée le rôle système « admin » de chaque organisation et l'attribue aux propriétaires.
 *
 * Idempotent : rejouable sans créer de doublon, y compris hors environnement local.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissionIds = Permission::pluck('id');

        Organization::each(function (Organization $organization) use ($permissionIds): void {
            $adminRole = Role::firstOrCreate(
                ['organization_id' => $organization->id, 'code' => 'admin'],
                ['name' => 'Administrateur', 'scope' => 'organization', 'is_system' => true, 'status' => 'active']
            );

            foreach ($permissionIds as $permissionId) {
                RolePermission::firstOrCreate(['role_id' => $adminRole->id, 'permission_id' => $permissionId]);
            }

            $this->attachToOwners($organization, $adminRole);
        });
    }

    private function attachToOwners(Organization $organization, Role $adminRole): void
    {
        OrganizationUser::where('organization_id', $organization->id)
            ->where('is_owner', true)
            ->each(static function (OrganizationUser $membership) use ($adminRole): void {
                UserRole::firstOrCreate([
                    'organization_user_id' => $membership->id,
                    'role_id' => $adminRole->id,
                ]);
            });
    }
}
