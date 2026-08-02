<?php

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->agency = Agency::where('organization_id', $this->organization->id)->firstOrFail();
});

describe('depots', function (): void {
    it('lists the depots of an agency', function (): void {
        Depot::factory(2)->forAgency($this->agency)->create();

        $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->getJson("/api/v1/agencies/{$this->agency->id}/depots")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('creates a depot', function (): void {
        $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->postJson("/api/v1/agencies/{$this->agency->id}/depots", ['code' => 'north', 'name' => 'Dépôt Nord'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'north');
    });

    it('shows, updates and deletes a depot', function (): void {
        $depot = Depot::factory()->forAgency($this->agency)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/agencies/{$this->agency->id}/depots/{$depot->id}")->assertOk();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/agencies/{$this->agency->id}/depots/{$depot->id}", ['name' => 'Dépôt renommé'])
            ->assertOk()->assertJsonPath('data.name', 'Dépôt renommé');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/agencies/{$this->agency->id}/depots/{$depot->id}")->assertNoContent();

        $this->assertDatabaseMissing('depots', ['id' => $depot->id]);
    });

    it('rejects a depot from an agency of another organization', function (): void {
        $foreignAgency = Agency::factory()->create();
        $foreignDepot = Depot::factory()->forAgency($foreignAgency)->create();

        $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->getJson("/api/v1/agencies/{$foreignAgency->id}/depots/{$foreignDepot->id}")
            ->assertForbidden();
    });

    it('rejects a forbidden sort column on the depot list', function (): void {
        $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->getJson("/api/v1/agencies/{$this->agency->id}/depots?sort=agency_id")
            ->assertStatus(422);
    });
});
