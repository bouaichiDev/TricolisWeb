<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Models\CustomerUserRole;
use App\Modules\CustomerPortal\Support\CustomerPortalRoles;
use App\Modules\Customers\Enums\CustomerStatus;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Enums\UserStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Deux clients de démonstration et leurs comptes de portail : de quoi
 * vérifier, à la main comme dans les parcours Playwright, qu'IKEA ne voit
 * jamais QOQA.
 *
 * Réservé aux environnements local et de test. Rejouable sans doublon.
 */
class DemoCustomerPortalSeeder extends Seeder
{
    /** @var array<string, array{name: string, members: array<string, array{string, string, string, bool}>}> */
    public const array CUSTOMERS = [
        'ikea' => [
            'name' => 'IKEA',
            'members' => [
                'admin@ikea.test' => ['Ingrid', 'Admin', CustomerPortalRoles::ADMIN, true],
                'orders@ikea.test' => ['Olaf', 'Commandes', CustomerPortalRoles::ORDER_MANAGER, false],
                'viewer@ikea.test' => ['Vera', 'Lecture', CustomerPortalRoles::VIEWER, false],
            ],
        ],
        'qoqa' => [
            'name' => 'QOQA',
            'members' => [
                'admin@qoqa.test' => ['Quentin', 'Admin', CustomerPortalRoles::ADMIN, true],
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

        foreach (self::CUSTOMERS as $code => $definition) {
            $customer = Customer::firstOrCreate(
                ['organization_id' => $organization->id, 'code' => $code],
                // Suivi ouvert : le calendrier, le suivi et le lien public
                // (Phase 3) se vérifient sur ces deux clients.
                ['name' => $definition['name'], 'status' => CustomerStatus::ACTIVE, 'tracking_enabled' => true],
            );

            foreach ($definition['members'] as $email => [$firstName, $lastName, $roleCode, $isOwner]) {
                $this->member($customer, $email, $firstName, $lastName, $roleCode, $isOwner);
            }
        }
    }

    private function member(Customer $customer, string $email, string $firstName, string $lastName, string $roleCode, bool $isOwner): void
    {
        $user = User::firstOrCreate(['email' => $email], [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'password' => Hash::make((string) config('tricolis.development_password')),
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $membership = CustomerUser::firstOrCreate(
            ['customer_id' => $customer->id, 'user_id' => $user->id],
            ['status' => UserStatus::ACTIVE, 'is_owner' => $isOwner, 'invited_at' => now(), 'activated_at' => now()],
        );

        $role = Role::where('organization_id', $customer->organization_id)->where('code', $roleCode)->first();

        if ($role !== null) {
            CustomerUserRole::firstOrCreate(['customer_user_id' => $membership->id, 'role_id' => $role->id]);
        }
    }
}
