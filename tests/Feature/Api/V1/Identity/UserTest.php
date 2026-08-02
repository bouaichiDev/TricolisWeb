<?php

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Enums\UserStatus;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
});

describe('users', function (): void {
    it('lists only the users of the active organization', function (): void {
        OrganizationUser::factory(2)->forOrganization($this->organization)->create();
        OrganizationUser::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('searches users by name and email', function (): void {
        $target = User::factory()->create(['first_name' => 'Zoé', 'email' => 'zoe@tricolis.dev']);
        OrganizationUser::factory()->forOrganization($this->organization)->create(['user_id' => $target->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/users?search=zoe')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'zoe@tricolis.dev');
    });

    it('creates a user attached to the active organization', function (): void {
        $role = Role::factory()->forOrganization($this->organization)->create();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/users', [
                'firstName' => 'Nadia',
                'lastName' => 'Berrada',
                'email' => 'nadia@tricolis.dev',
                'password' => 'Password!2345',
                'password_confirmation' => 'Password!2345',
                'preferredLanguage' => 'fr',
                'isOwner' => false,
                'isPrimary' => true,
                'status' => UserStatus::ACTIVE->value,
                'roleIds' => [$role->id],
            ]);

        $response->assertCreated()->assertJsonPath('data.email', 'nadia@tricolis.dev');

        $this->assertDatabaseHas('organization_users', [
            'organization_id' => $this->organization->id,
            'user_id' => $response->json('data.id'),
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'entity_type' => 'user']);
    });

    it('never returns the password hash', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/users/{$this->user->id}");

        $response->assertOk();
        expect(array_keys($response->json('data')))->not->toContain('password')->not->toContain('passwordHash');
    });

    it('rejects a role from another organization at creation', function (): void {
        $foreignRole = Role::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/users', [
                'firstName' => 'Karim',
                'lastName' => 'Alaoui',
                'email' => 'karim@tricolis.dev',
                'password' => 'Password!2345',
                'password_confirmation' => 'Password!2345',
                'preferredLanguage' => 'fr',
                'isOwner' => false,
                'isPrimary' => true,
                'status' => UserStatus::ACTIVE->value,
                'roleIds' => [$foreignRole->id],
            ])
            ->assertStatus(422);
    });

    it('rejects a duplicated email', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/users', [
                'firstName' => 'Doublon',
                'lastName' => 'Test',
                'email' => $this->user->email,
                'password' => 'Password!2345',
                'password_confirmation' => 'Password!2345',
                'preferredLanguage' => 'fr',
                'isOwner' => false,
                'isPrimary' => true,
                'status' => UserStatus::ACTIVE->value,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    });

    it('updates a user and audits the change', function (): void {
        $membership = OrganizationUser::factory()->forOrganization($this->organization)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/users/{$membership->user_id}", ['firstName' => 'Prénom modifié'])
            ->assertOk()
            ->assertJsonPath('data.firstName', 'Prénom modifié');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'updated',
            'entity_type' => 'user',
            'entity_id' => $membership->user_id,
        ]);
    });

    it('disables a user and revokes their tokens', function (): void {
        $membership = OrganizationUser::factory()->forOrganization($this->organization)->create();
        $target = User::findOrFail($membership->user_id);
        $target->createToken('poste');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/users/{$target->id}")
            ->assertNoContent();

        expect($target->fresh()->status)->toBe(UserStatus::DISABLED)
            ->and($target->tokens()->count())->toBe(0);
    });

    it('hides a user from another organization', function (): void {
        $foreign = Organization::factory()->create();
        $membership = OrganizationUser::factory()->forOrganization($foreign)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/users/{$membership->user_id}")
            ->assertForbidden();
    });

    it('rejects a forbidden sort column', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/users?sort=password')
            ->assertStatus(422);
    });
});
