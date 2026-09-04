<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Identity\Services\PlatformAccess;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Enums\RoleScope;
use Illuminate\Database\Seeder;

/**
 * Rôles livrés avec l'application.
 *
 * Deux niveaux, et la frontière entre eux est ce que ce seeder établit :
 *
 * - le rôle plateforme `superadmin`, sans organisation, qui administre Tricolis ;
 * - le rôle `admin` de chaque organisation, qui administre **son** organisme.
 *
 * Le rôle `admin` ne reçoit plus les permissions réservées à la plateforme. Il
 * les recevait auparavant — `Permission::pluck('id')` les incluait toutes — ce
 * qui donnait à tout propriétaire d'organisme le droit de créer d'autres
 * organisations.
 *
 * Le rôle `superadmin` n'est attribué à personne ici : désigner un
 * administrateur de plateforme est une décision d'exploitation, pas une valeur
 * par défaut. Le rattacher automatiquement au premier compte venu recréerait
 * exactement le problème corrigé.
 *
 * Idempotent : rejouable sans créer de doublon.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPlatformRole();
        $this->seedOrganizationRoles();
    }

    /** Code du role porte par les comptes de chauffeur. */
    public const string DRIVER_CODE = 'driver';

    private function seedPlatformRole(): void
    {
        $role = Role::firstOrCreate(
            ['organization_id' => null, 'code' => 'superadmin'],
            [
                'name' => 'Administrateur plateforme',
                'scope' => RoleScope::PLATFORM->value,
                'is_system' => true,
                'status' => 'active',
            ]
        );

        $this->syncPermissions($role, Permission::pluck('id')->all());
    }

    private function seedOrganizationRoles(): void
    {
        $organizationalPermissionIds = Permission::whereNotIn('code', PlatformAccess::PLATFORM_PERMISSIONS)
            ->pluck('id')
            ->all();

        Organization::each(function (Organization $organization) use ($organizationalPermissionIds): void {
            $adminRole = Role::firstOrCreate(
                ['organization_id' => $organization->id, 'code' => 'admin'],
                [
                    'name' => 'Administrateur',
                    'scope' => RoleScope::ORGANIZATION->value,
                    'is_system' => true,
                    'status' => 'active',
                ]
            );

            $this->syncPermissions($adminRole, $organizationalPermissionIds);
            $this->revokePlatformPermissions($adminRole);
            $this->attachToOwners($organization, $adminRole);

            // Role du chauffeur : il identifie qui peut etre rattache a un
            // chauffeur, et **n'ouvre rien** dans le back-office. Le chauffeur
            // travaille sur le terminal mobile ; lui donner des permissions ici
            // reviendrait a ouvrir des ecrans qu'il n'a pas a voir.
            Role::firstOrCreate(
                ['organization_id' => $organization->id, 'code' => self::DRIVER_CODE],
                [
                    'name' => 'Chauffeur',
                    'scope' => RoleScope::ORGANIZATION->value,
                    'is_system' => true,
                    'status' => 'active',
                ]
            );
        });
    }

    /**
     * @param  array<int, string>  $permissionIds
     */
    private function syncPermissions(Role $role, array $permissionIds): void
    {
        foreach ($permissionIds as $permissionId) {
            RolePermission::firstOrCreate(['role_id' => $role->id, 'permission_id' => $permissionId]);
        }
    }

    /**
     * Retire les permissions plateforme d'un rôle d'organisation.
     *
     * Nécessaire sur une base déjà semée : les rôles `admin` créés avant cette
     * correction les portent, et un simple `firstOrCreate` ne les enlèverait pas.
     */
    private function revokePlatformPermissions(Role $role): void
    {
        $platformIds = Permission::whereIn('code', PlatformAccess::PLATFORM_PERMISSIONS)->pluck('id');

        RolePermission::where('role_id', $role->id)
            ->whereIn('permission_id', $platformIds)
            ->delete();
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
