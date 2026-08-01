<?php

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Providers\Models\Provider;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->provider = Provider::factory()->forOrganization($this->organization)->create();
    $this->type = VehicleType::factory()->forOrganization($this->organization)->create();
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

describe('vehicle types', function (): void {
    it('creates, reads, updates and deletes a vehicle type', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/vehicle-types', ['code' => 'VT-NEW', 'name' => 'Fourgon 20m3', 'status' => 'active'])
            ->assertCreated()->assertJsonPath('data.code', 'VT-NEW');
        $id = $response->json('data.id');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/vehicle-types/$id")->assertOk();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/vehicle-types/$id", ['name' => 'Renommé'])
            ->assertOk()->assertJsonPath('data.name', 'Renommé');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/vehicle-types/$id")->assertNoContent();

        $this->assertDatabaseHas('audit_logs', ['action' => 'vehicle_type.created', 'entity_type' => 'vehicle_type']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'vehicle_type.deleted']);
    });

    it('refuses deletion when a vehicle uses the type', function (): void {
        Vehicle::factory()->forProvider($this->provider)->ofType($this->type)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/vehicle-types/{$this->type->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('vehicle_types', ['id' => $this->type->id]);
    });

    it('refuses a duplicated code and hides types of other organizations', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/vehicle-types', ['code' => $this->type->code, 'name' => 'Doublon', 'status' => 'active'])
            ->assertStatus(422)->assertJsonValidationErrors('code');

        $foreign = VehicleType::factory()->create();
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/vehicle-types/{$foreign->id}")->assertNotFound();
    });

    it('searches and filters vehicle types', function (): void {
        VehicleType::factory()->forOrganization($this->organization)->create(['name' => 'Frigorifique', 'status' => 'inactive']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/vehicle-types?search=Frigo')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/vehicle-types?status=inactive')->assertOk()->assertJsonCount(1, 'data');
    });
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
        $foreignType = VehicleType::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/vehicles', ($this->payload)(['vehicleTypeId' => $foreignType->id]))
            ->assertStatus(422)->assertJsonValidationErrors('vehicleTypeId');
    });

    it('refuses a provider and a type from two different organizations', function (): void {
        $other = Organization::factory()->create();
        $otherProvider = Provider::factory()->forOrganization($other)->create();
        $otherType = VehicleType::factory()->forOrganization($other)->create();

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
