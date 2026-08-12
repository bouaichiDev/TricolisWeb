<?php

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Services\PlatformAccess;

beforeEach(function (): void {
    $this->seed();
});

describe('transporter registration', function (): void {
    it('creates the owner and their organization atomically', function (): void {
        $response = $this->postJson('/api/v1/auth/register', [
            'firstName' => 'Sara',
            'lastName' => 'Amrani',
            'email' => 'sara@example.com',
            'phone' => '+212600000000',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'organization' => [
                'name' => 'Atlas Transport',
                'legalName' => 'Atlas Transport SARL',
                'registrationNumber' => 'RC-12345',
                'taxNumber' => 'ICE-98765',
                'timezone' => 'Africa/Casablanca',
                'currencyCode' => 'MAD',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'sara@example.com')
            ->assertJsonPath('data.organization.code', 'atlas-transport')
            ->assertJsonPath('data.organization.currencyCode', 'MAD')
            ->assertJsonStructure(['data' => ['user', 'organization', 'token']]);

        $userId = $response->json('data.user.id');
        $organizationId = $response->json('data.organization.id');

        $this->assertDatabaseHas('organization_users', [
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'is_owner' => true,
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('roles', [
            'organization_id' => $organizationId,
            'code' => 'admin',
        ]);
        // Le rôle `admin` de l'organisation créée reçoit tout **sauf** les
        // permissions réservées à la plateforme : s'inscrire ne donne pas le
        // droit de créer d'autres organisations.
        $organizational = Permission::whereNotIn('code', PlatformAccess::PLATFORM_PERMISSIONS)->count();

        $this->assertDatabaseCount(
            'role_permissions',
            Permission::count()          // rôle plateforme semé
            + $organizational * 2        // rôle admin de l'organisation semée et de la nouvelle
        );

        $registeredRole = Role::where('organization_id', $organizationId)->firstOrFail();

        foreach (PlatformAccess::PLATFORM_PERMISSIONS as $code) {
            $this->assertDatabaseMissing('role_permissions', [
                'role_id' => $registeredRole->id,
                'permission_id' => Permission::where('code', $code)->value('id'),
            ]);
        }

        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $userId]);
    });

    it('rejects duplicate accounts and invalid organization data', function (): void {
        $payload = [
            'firstName' => 'Sara',
            'lastName' => 'Amrani',
            'email' => 'admin@tricolis.dev',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'organization' => [
                'name' => 'Atlas Transport',
                'currencyCode' => 'EURO',
            ],
        ];

        $this->postJson('/api/v1/auth/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'organization.currencyCode']);
    });
});
