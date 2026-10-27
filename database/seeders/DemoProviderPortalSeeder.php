<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Models\ProviderUserRole;
use App\Modules\ProviderPortal\Support\ProviderPortalRoles;
use App\Modules\Providers\Models\Provider;
use App\Shared\Enums\UserStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Deux fournisseurs de démonstration et leurs comptes de portail : de quoi
 * vérifier, à la main comme dans les tests, qu'Alpha ne voit jamais Beta.
 *
 * Réservé aux environnements local et de test. Rejouable sans doublon.
 */
class DemoProviderPortalSeeder extends Seeder
{
    /** @var array<string, array{name: string, members: array<string, array{string, string, string}>}> */
    public const array PROVIDERS = [
        'transports-alpha' => [
            'name' => 'Transports Alpha',
            'members' => [
                'admin@transports-alpha.test' => ['Alice', 'Admin', ProviderPortalRoles::ADMIN],
                'viewer@transports-alpha.test' => ['Victor', 'Lecture', ProviderPortalRoles::VIEWER],
            ],
        ],
        'transports-beta' => [
            'name' => 'Transports Beta',
            'members' => [
                'admin@transports-beta.test' => ['Bruno', 'Admin', ProviderPortalRoles::ADMIN],
            ],
        ],
    ];

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $organization = Organization::where('code', DevelopmentOrganizationSeeder::ORGANIZATION_CODE)->first();

        if ($organization === null) {
            return;
        }

        foreach (self::PROVIDERS as $code => $definition) {
            $provider = Provider::firstOrCreate(
                ['organization_id' => $organization->id, 'code' => $code],
                ['name' => $definition['name'], 'status' => 'active'],
            );

            foreach ($definition['members'] as $email => [$firstName, $lastName, $roleCode]) {
                $this->member($provider, $email, $firstName, $lastName, $roleCode);
            }
        }
    }

    private function member(Provider $provider, string $email, string $firstName, string $lastName, string $roleCode): void
    {
        $user = User::firstOrCreate(['email' => $email], [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'password' => Hash::make((string) config('tricolis.development_password')),
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $membership = ProviderUser::firstOrCreate(
            ['provider_id' => $provider->id, 'user_id' => $user->id],
            ['status' => UserStatus::ACTIVE, 'invited_at' => now(), 'activated_at' => now()],
        );

        $role = Role::where('organization_id', $provider->organization_id)->where('code', $roleCode)->first();

        if ($role !== null) {
            ProviderUserRole::firstOrCreate(['provider_user_id' => $membership->id, 'role_id' => $role->id]);
        }
    }
}
