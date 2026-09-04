<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Enums\RoleScope;
use App\Shared\Enums\UserStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Compte d'administration de la plateforme, pour le développement.
 *
 * **Distinct du compte `admin@tricolis.dev`**, et c'est le point important : un
 * compte plateforme ne voit ni clients, ni agences, ni son organisation. Donner
 * l'autorité plateforme au compte administrateur d'organisme lui ferait perdre
 * tout ce qu'il sert à tester. Deux comptes, deux périmètres.
 *
 * Le rattachement à l'organisation de développement n'est qu'un support
 * technique : `user_roles` pointe vers `organization_users`, un rôle doit donc
 * s'accrocher à une appartenance. Le compte y est **membre simple**, pas
 * propriétaire, pour qu'aucun droit d'organisme ne lui vienne de là.
 *
 * **Environnement local uniquement**, pas `testing` : la suite vérifie que le
 * rôle plateforme n'est attaché à personne par défaut, ce qui est la garantie
 * qu'aucun compte ne l'obtient sans décision explicite. Les tests qui ont
 * besoin d'un administrateur de plateforme le fabriquent eux-mêmes via
 * `makePlatformAdmin()`.
 *
 * En production, l'autorité se confère par
 * `php artisan tricolis:platform-admin {email}`.
 */
class PlatformAdminSeeder extends Seeder
{
    public const string EMAIL = 'superadmin@tricolis.dev';

    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $organization = Organization::where('code', DevelopmentOrganizationSeeder::ORGANIZATION_CODE)->first();
        $role = Role::where('scope', RoleScope::PLATFORM->value)->whereNull('organization_id')->first();

        if ($organization === null || $role === null) {
            return;
        }

        $user = User::firstOrCreate(
            ['email' => self::EMAIL],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'preferred_language' => 'fr',
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
                'password' => Hash::make(config('tricolis.development_password')),
            ]
        );

        $membership = OrganizationUser::firstOrCreate(
            ['organization_id' => $organization->id, 'user_id' => $user->id],
            [
                'is_owner' => false,
                'is_primary' => true,
                'status' => UserStatus::ACTIVE,
                'joined_at' => now(),
            ]
        );

        UserRole::firstOrCreate([
            'organization_user_id' => $membership->id,
            'role_id' => $role->id,
        ]);
    }
}
