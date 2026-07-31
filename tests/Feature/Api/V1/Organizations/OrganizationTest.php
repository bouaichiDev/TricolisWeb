<?php

use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
});

describe('organizations', function (): void {
    it('lists only the organizations the user belongs to', function (): void {
        Organization::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/organizations');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->organization->id);
    });

    it('creates an organization and makes the author its owner', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/organizations', [
            'code' => 'new-org',
            'name' => 'Nouvelle organisation',
        ]);

        $response->assertCreated()->assertJsonPath('data.code', 'new-org');

        $this->assertDatabaseHas('organization_users', [
            'organization_id' => $response->json('data.id'),
            'user_id' => $this->user->id,
            'is_owner' => true,
        ]);
    });

    it('shows an organization the user belongs to', function (): void {
        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}")
            ->assertOk()
            ->assertJsonPath('data.code', 'tricolis-dev');
    });

    it('updates an organization', function (): void {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/organizations/{$this->organization->id}", ['name' => 'Tricolis Modifié'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Tricolis Modifié');

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'action' => 'updated',
            'entity_type' => 'organization',
        ]);
    });

    it('deletes an organization owned by the user', function (): void {
        $organization = Organization::factory()->create();
        OrganizationUser::factory()->owner()->forOrganization($organization)->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$organization->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
    });

    it('hides an organization the user does not belong to', function (): void {
        $other = Organization::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$other->id}")
            ->assertForbidden();
    });

    it('forbids deleting an organization the user does not own', function (): void {
        $organization = Organization::factory()->create();
        OrganizationUser::factory()->forOrganization($organization)->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$organization->id}")
            ->assertForbidden();
    });
});
