<?php

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockLocation;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $this->depot = Depot::factory()->create(['agency_id' => $this->agency->id]);

    $this->payload = fn (array $o = []): array => array_merge([
        'depotId' => $this->depot->id,
        'locationCode' => 'A-01-01',
        'status' => 'active',
    ], $o);
});

describe('stock locations creation', function (): void {
    it('creates a location in a depot of the active organization', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-locations', ($this->payload)([
                'zoneCode' => 'A', 'aisle' => '1', 'rack' => '1', 'level' => '1',
            ]))
            ->assertCreated()->assertJsonPath('data.locationCode', 'A-01-01');

        $this->assertDatabaseHas('stock_locations', [
            'id' => $response->json('data.id'),
            'depot_id' => $this->depot->id,
        ]);
    });

    it('refuses a depot from another organization', function (): void {
        $foreignDepot = Depot::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-locations', ($this->payload)(['depotId' => $foreignDepot->id]))
            ->assertStatus(422)->assertJsonValidationErrors('depotId');
    });

    it('refuses a duplicated code in the same depot but allows it elsewhere', function (): void {
        StockLocation::factory()->forDepot($this->depot)->create(['location_code' => 'DUP-1']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-locations', ($this->payload)(['locationCode' => 'DUP-1']))
            ->assertStatus(422)->assertJsonValidationErrors('locationCode');

        $otherDepot = Depot::factory()->create(['agency_id' => $this->agency->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-locations', ($this->payload)([
                'depotId' => $otherDepot->id, 'locationCode' => 'DUP-1',
            ]))
            ->assertCreated();
    });

    it('creates a child of an existing location', function (): void {
        $parent = StockLocation::factory()->forDepot($this->depot)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-locations', ($this->payload)(['parentLocationId' => $parent->id]))
            ->assertCreated()->assertJsonPath('data.parentLocationId', $parent->id);
    });

    it('refuses a parent from another depot', function (): void {
        $otherDepot = Depot::factory()->create(['agency_id' => $this->agency->id]);
        $foreignParent = StockLocation::factory()->forDepot($otherDepot)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-locations', ($this->payload)(['parentLocationId' => $foreignParent->id]))
            ->assertStatus(422)->assertJsonValidationErrors('parentLocationId');
    });
});

describe('stock locations hierarchy', function (): void {
    it('refuses a location as its own parent', function (): void {
        $location = StockLocation::factory()->forDepot($this->depot)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/stock-locations/{$location->id}", ['parentLocationId' => $location->id])
            ->assertStatus(422)->assertJsonValidationErrors('parentLocationId');
    });

    it('refuses a direct descendant as parent', function (): void {
        $parent = StockLocation::factory()->forDepot($this->depot)->create();
        $child = StockLocation::factory()->childOf($parent)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/stock-locations/{$parent->id}", ['parentLocationId' => $child->id])
            ->assertStatus(422)->assertJsonValidationErrors('parentLocationId');
    });

    it('refuses an indirect descendant as parent', function (): void {
        $grandParent = StockLocation::factory()->forDepot($this->depot)->create();
        $parent = StockLocation::factory()->childOf($grandParent)->create();
        $child = StockLocation::factory()->childOf($parent)->create();

        // Rattacher le grand-parent a son petit-enfant fermerait la boucle.
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/stock-locations/{$grandParent->id}", ['parentLocationId' => $child->id])
            ->assertStatus(422)->assertJsonValidationErrors('parentLocationId');
    });

    it('allows a legitimate reorganisation', function (): void {
        $first = StockLocation::factory()->forDepot($this->depot)->create();
        $second = StockLocation::factory()->forDepot($this->depot)->create();
        $leaf = StockLocation::factory()->childOf($first)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/stock-locations/{$leaf->id}", ['parentLocationId' => $second->id])
            ->assertOk()->assertJsonPath('data.parentLocationId', $second->id);
    });

    it('returns the tree derived from the table', function (): void {
        $root = StockLocation::factory()->forDepot($this->depot)->create(['location_code' => 'A']);
        $child = StockLocation::factory()->childOf($root)->create(['location_code' => 'A-1']);
        StockLocation::factory()->childOf($child)->create(['location_code' => 'A-1-1']);
        StockLocation::factory()->forDepot($this->depot)->create(['location_code' => 'B']);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/stock-locations/tree')->assertOk();

        expect($response->json('data'))->toHaveCount(2)
            ->and($response->json('data.0.locationCode'))->toBe('A')
            ->and($response->json('data.0.children.0.locationCode'))->toBe('A-1')
            ->and($response->json('data.0.children.0.children.0.locationCode'))->toBe('A-1-1');
    });

    it('restricts the tree to a depot when asked', function (): void {
        StockLocation::factory()->forDepot($this->depot)->create();
        $otherDepot = Depot::factory()->create(['agency_id' => $this->agency->id]);
        StockLocation::factory()->forDepot($otherDepot)->create();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/stock-locations/tree?depotId={$this->depot->id}")->assertOk();

        expect($response->json('data'))->toHaveCount(1);
    });
});

describe('stock locations deletion', function (): void {
    it('deletes an empty location', function (): void {
        $location = StockLocation::factory()->forDepot($this->depot)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/stock-locations/{$location->id}")->assertNoContent();

        $this->assertDatabaseMissing('stock_locations', ['id' => $location->id]);
    });

    it('refuses to delete a location with children', function (): void {
        $parent = StockLocation::factory()->forDepot($this->depot)->create();
        StockLocation::factory()->childOf($parent)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/stock-locations/{$parent->id}")->assertStatus(409);
    });

    it('refuses to delete a location that carries stock', function (): void {
        $location = StockLocation::factory()->forDepot($this->depot)->create();
        $item = StockItem::factory()->create();
        StockBalance::factory()->at($item, $location)->withQuantity(3)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/stock-locations/{$location->id}")->assertStatus(409);
    });
});

describe('stock locations scope and list', function (): void {
    it('hides a location from another organization', function (): void {
        $foreign = StockLocation::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/stock-locations/{$foreign->id}")->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/stock-locations/{$foreign->id}")->assertNotFound();
    });

    it('lists, searches and filters', function (): void {
        StockLocation::factory()->forDepot($this->depot)->create(['zone_code' => 'ZZZ', 'status' => 'blocked']);
        StockLocation::factory(2)->forDepot($this->depot)->create();
        StockLocation::factory(3)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/stock-locations')->assertOk()->assertJsonCount(3, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/stock-locations?search=ZZZ')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/stock-locations?status=blocked')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/stock-locations?sort=depot_id')->assertStatus(422);
    });

    it('audits creation and deletion', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-locations', ($this->payload)())->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'stock_location.created', 'entity_type' => 'stock_location', 'entity_id' => $id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/stock-locations/$id")->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'stock_location.deleted', 'entity_id' => $id]);
    });
});
