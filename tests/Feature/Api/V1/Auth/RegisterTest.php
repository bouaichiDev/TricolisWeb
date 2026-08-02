<?php

use App\Modules\Identity\Models\Permission;

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
        $this->assertDatabaseCount('role_permissions', Permission::count() * 2);
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
