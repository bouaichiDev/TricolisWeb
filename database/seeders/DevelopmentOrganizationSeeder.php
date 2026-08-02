<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Enums\OrganizationStatus;
use App\Shared\Enums\UserStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Organisation Tricolis de développement et son utilisateur administrateur.
 *
 * Les identifiants sont documentés dans docs/backend/local-development.md
 * et ne sont créés qu'en environnement local ou de test.
 */
class DevelopmentOrganizationSeeder extends Seeder
{
    public const string ORGANIZATION_CODE = 'tricolis-dev';

    public const string ADMIN_EMAIL = 'admin@tricolis.dev';

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $organization = Organization::firstOrCreate(
            ['code' => self::ORGANIZATION_CODE],
            [
                'name' => 'Tricolis Dev',
                'legal_name' => 'Tricolis Dev SARL',
                'email' => 'contact@tricolis.dev',
                'preferred_language' => 'fr',
                'timezone' => 'Europe/Paris',
                'currency_code' => 'EUR',
                'status' => OrganizationStatus::ACTIVE,
                'settings' => [],
            ]
        );

        $user = User::firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'first_name' => 'Admin',
                'last_name' => 'Tricolis',
                'phone' => '+33100000000',
                'preferred_language' => 'fr',
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
                'password' => Hash::make(config('tricolis.development_password')),
            ]
        );

        OrganizationUser::firstOrCreate(
            ['organization_id' => $organization->id, 'user_id' => $user->id],
            [
                'is_owner' => true,
                'is_primary' => true,
                'status' => UserStatus::ACTIVE,
                'joined_at' => now(),
            ]
        );
    }
}
