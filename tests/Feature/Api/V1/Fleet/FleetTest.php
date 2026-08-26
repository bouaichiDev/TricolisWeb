<?php

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Providers\Models\Provider;
use App\Modules\Types\Models\TypeItem;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->provider = Provider::factory()->forOrganization($this->organization)->create();
    $this->type = TypeItem::factory()->forOrganization($this->organization)->ofSystemType('vehicle')->create();
    $this->payload = fn (array $o = []): array => array_merge([
        'providerId' => $this->provider->id,
        'vehicleTypeId' => $this->type->id,
        'code' => 'VEH-NEW',
        'registrationNumber' => 'AA-123-BB',
        'payloadCapacity' => 3500,
        'volumeCapacity' => 22.5,
        'palletCapacity' => 8,
        'status' => 'active',
    ], $o);
});

describe('vehicles CRUD', function (): void {
    it('creates a vehicle', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/vehicles', ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.registrationNumber', 'AA-123-BB')
            ->assertJsonPath('data.palletCapacity', 8);

        $this->assertDatabaseHas('audit_logs', ['action' => 'vehicle.created', 'entity_type' => 'vehicle']);
    });

    it('reads, updates and deletes a vehicle', function (): void {
        $vehicle = Vehicle::factory()->forProvider($this->provider)->ofType($this->type)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/vehicles/{$vehicle->id}")->assertOk();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/vehicles/{$vehicle->id}", ['status' => 'maintenance'])
            ->assertOk()->assertJsonPath('data.status', 'maintenance');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/vehicles/{$vehicle->id}")->assertNoContent();
    });
});

describe('vehicles invariants', function (): void {
    it('refuses a provider from another organization', function (): void {
        $foreignProvider = Provider::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/vehicles', ($this->payload)(['providerId' => $foreignProvider->id]))
            ->assertStatus(422)->assertJsonValidationErrors('providerId');
    });

    it('refuses a vehicle type from another organization', function (): void {
        $foreignType = TypeItem::factory()->ofSystemType('vehicle')->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/vehicles', ($this->payload)(['vehicleTypeId' => $foreignType->id]))
            ->assertStatus(422)->assertJsonValidationErrors('vehicleTypeId');
    });

    it('refuses a provider and a type from two different organizations', function (): void {
        $other = Organization::factory()->create();
        $otherProvider = Provider::factory()->forOrganization($other)->create();
        $otherType = TypeItem::factory()->forOrganization($other)->ofSystemType('vehicle')->create();

        // Les deux sont hors organisation active : la creation est refusee, et
        // l'invariant provider.organization = type.organization reste tenu.
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/vehicles', ($this->payload)([
                'providerId' => $otherProvider->id,
                'vehicleTypeId' => $otherType->id,
            ]))
            ->assertStatus(422);

        // Un seul des deux hors perimetre est refuse tout autant.
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/vehicles', ($this->payload)(['vehicleTypeId' => $otherType->id]))
            ->assertStatus(422)->assertJsonValidationErrors('vehicleTypeId');
    });

    it('refuses a duplicated code for the same provider', function (): void {
        Vehicle::factory()->forProvider($this->provider)->ofType($this->type)->create(['code' => 'VEH-1']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/vehicles', ($this->payload)(['code' => 'VEH-1']))
            ->assertStatus(422)->assertJsonValidationErrors('code');
    });

    it('refuses a duplicated registration number globally', function (): void {
        Vehicle::factory()->create(['registration_number' => 'ZZ-999-ZZ']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/vehicles', ($this->payload)(['registrationNumber' => 'ZZ-999-ZZ']))
            ->assertStatus(422)->assertJsonValidationErrors('registrationNumber');
    });

    it('refuses negative capacities', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/vehicles', ($this->payload)(['payloadCapacity' => -1]))
            ->assertStatus(422)->assertJsonValidationErrors('payloadCapacity');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/vehicles', ($this->payload)(['palletCapacity' => -3]))
            ->assertStatus(422)->assertJsonValidationErrors('palletCapacity');
    });

    it('hides a vehicle of another organization', function (): void {
        $foreign = Vehicle::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/vehicles/{$foreign->id}")->assertNotFound();
    });
});

describe('vehicles list', function (): void {
    it('searches, filters by capacity and rejects a forbidden sort', function (): void {
        Vehicle::factory()->forProvider($this->provider)->ofType($this->type)
            ->create(['registration_number' => 'BB-777-CC', 'payload_capacity' => 20000, 'pallet_capacity' => 33]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/vehicles?search=BB-777')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/vehicles?payloadCapacityMin=19000')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/vehicles?palletCapacityMin=40')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/vehicles?sort=provider_id')->assertStatus(422);
    });

    it('filters by provider and by vehicle type', function (): void {
        Vehicle::factory(2)->forProvider($this->provider)->ofType($this->type)->create();
        Vehicle::factory(2)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/vehicles?providerId={$this->provider->id}")->assertOk()->assertJsonCount(2, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/vehicles?vehicleTypeId={$this->type->id}")->assertOk()->assertJsonCount(2, 'data');
    });
});

/**
 * Un transporteur possède ses propres camions : le fournisseur est facultatif,
 * et c'est le véhicule qui porte alors son organisation.
 */
describe('véhicule du transporteur', function (): void {
    it('creates a vehicle without any provider', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/vehicles', ($this->payload)(['providerId' => null]))
            ->assertCreated()
            ->assertJsonPath('data.providerId', null)
            ->assertJsonPath('data.organizationId', $this->organization->id);

        $this->assertDatabaseHas('vehicles', [
            'id' => $response->json('data.id'),
            'provider_id' => null,
            'organization_id' => $this->organization->id,
        ]);
    });

    it('lists it alongside the vehicles of providers', function (): void {
        $own = Vehicle::factory()->forOrganization($this->organization)->withoutProvider()
            ->create(['code' => 'VEH-OWN']);
        $supplied = Vehicle::factory()->forProvider($this->provider)->create(['code' => 'VEH-SUP']);

        // Les codes plutot qu'un compte : l'organisation de developpement est
        // livree avec un vehicule de demonstration, qui ferait varier le total.
        $codes = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/vehicles')->assertOk()->json('data.*.code');

        expect($codes)->toContain($own->code, $supplied->code);
    });

    /**
     * Le code identifie un véhicule dans toute l'organisation : il était unique
     * par fournisseur, et deux `NULL` étant distincts pour MySQL, les véhicules
     * du transporteur auraient pu tous porter le même.
     */
    it('refuses a code already used elsewhere in the organization', function (): void {
        Vehicle::factory()->forProvider($this->provider)->create(['code' => 'VEH-DUP']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/vehicles', ($this->payload)(['providerId' => null, 'code' => 'VEH-DUP']))
            ->assertStatus(422)->assertJsonValidationErrors('code');
    });

    it('hides a vehicle of another organization', function (): void {
        $foreign = Vehicle::factory()->withoutProvider()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/vehicles/{$foreign->id}")->assertNotFound();
    });
});
