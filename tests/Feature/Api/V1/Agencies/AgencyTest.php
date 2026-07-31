<?php

use App\Modules\Agencies\Models\Agency;
use App\Modules\Organizations\Models\Organization;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
});

describe('agencies', function (): void {
    it('lists agencies for the active organization', function (): void {
        Agency::factory(2)->create(['organization_id' => $this->organization->id]);
        Agency::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->getJson('/api/v1/agencies');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('creates an agency', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->postJson('/api/v1/agencies', [
                'code' => 'new-agency',
                'name' => 'New Agency',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'new-agency');
    });

    it('updates an agency', function (): void {
        $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->patchJson("/api/v1/agencies/{$agency->id}", ['name' => 'Agence renommée'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Agence renommée');

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'action' => 'updated',
            'entity_type' => 'agency',
            'entity_id' => $agency->id,
        ]);
    });

    it('deletes an agency without depots', function (): void {
        $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->deleteJson("/api/v1/agencies/{$agency->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('agencies', ['id' => $agency->id]);
    });

    it('refuses deleting an agency that still owns depots', function (): void {
        $agency = Agency::where('organization_id', $this->organization->id)->firstOrFail();

        $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->deleteJson("/api/v1/agencies/{$agency->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('agencies', ['id' => $agency->id]);
    });

    it('refuses updating an agency of another organization', function (): void {
        $foreign = Agency::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->patchJson("/api/v1/agencies/{$foreign->id}", ['name' => 'Piratée'])
            ->assertForbidden();
    });

    it('rejects creating an agency in another organization', function (): void {
        $other = Organization::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Organization-Id' => $other->id])
            ->postJson('/api/v1/agencies', [
                'code' => 'new-agency',
                'name' => 'New Agency',
            ]);

        $response->assertForbidden();
    });
});
