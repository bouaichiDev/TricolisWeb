<?php

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Providers\Models\Provider;
use App\Modules\Types\Models\Type;
use App\Modules\Types\Models\TypeItem;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->api = fn (string $method, string $url, array $payload = []) => $this
        ->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->json($method, $url, $payload);
    $this->vehicleSource = Type::where('organization_id', $this->organization->id)
        ->where('code', 'vehicle')->firstOrFail();
});

describe('sources', function (): void {
    /**
     * Les trois sources structurelles existent des la creation de
     * l'organisation : sans elles, aucun vehicule ni colis ne pourrait etre
     * classe.
     */
    it('ships the three structural sources with every organization', function (): void {
        $codes = Type::where('organization_id', $this->organization->id)->pluck('code')->sort()->values();

        expect($codes->all())->toContain('grouping', 'package', 'vehicle');
    });

    it('creates a source of its own and lists it with its value count', function (): void {
        $id = ($this->api)('POST', '/api/v1/types', ['code' => 'couleur', 'name' => 'Couleur'])
            ->assertCreated()->assertJsonPath('data.isSystem', false)->json('data.id');

        ($this->api)('POST', '/api/v1/type-items', ['typeId' => $id, 'code' => 'rouge', 'name' => 'Rouge'])
            ->assertCreated();

        ($this->api)('GET', '/api/v1/types?search=couleur')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.itemCount', 1);
    });

    it('refuses a duplicated source code', function (): void {
        ($this->api)('POST', '/api/v1/types', ['code' => 'vehicle', 'name' => 'Doublon'])
            ->assertStatus(422)->assertJsonValidationErrors('code');
    });

    /**
     * Le schema designe ces sources par leur code : le renommer laisserait
     * `vehicles.vehicle_type_id` sans source, et la supprimer aussi.
     */
    it('keeps the code of a structural source and refuses its deletion', function (): void {
        ($this->api)('PATCH', "/api/v1/types/{$this->vehicleSource->id}", [
            'code' => 'autre', 'name' => 'Renomme',
        ])->assertOk()->assertJsonPath('data.code', 'vehicle')->assertJsonPath('data.name', 'Renomme');

        ($this->api)('DELETE', "/api/v1/types/{$this->vehicleSource->id}")->assertForbidden();
    });

    it('refuses to delete a source that still carries values', function (): void {
        $id = ($this->api)('POST', '/api/v1/types', ['code' => 'matiere', 'name' => 'Matiere'])
            ->assertCreated()->json('data.id');

        ($this->api)('POST', '/api/v1/type-items', ['typeId' => $id, 'code' => 'bois', 'name' => 'Bois'])
            ->assertCreated();

        ($this->api)('DELETE', "/api/v1/types/$id")->assertStatus(409);

        // Vidée, elle s'en va.
        $itemId = TypeItem::where('type_id', $id)->value('id');
        ($this->api)('DELETE', "/api/v1/type-items/$itemId")->assertNoContent();
        ($this->api)('DELETE', "/api/v1/types/$id")->assertNoContent();
    });

    it('hides the sources of other organizations', function (): void {
        $foreign = Type::factory()->create();

        ($this->api)('GET', "/api/v1/types/{$foreign->id}")->assertNotFound();
    });
});

describe('valeurs', function (): void {
    it('creates, reads, updates and deletes a value', function (): void {
        $id = ($this->api)('POST', '/api/v1/type-items', [
            'typeId' => $this->vehicleSource->id, 'code' => 'VL-3T5', 'name' => 'Vehicule leger',
        ])->assertCreated()->assertJsonPath('data.typeCode', 'vehicle')->json('data.id');

        ($this->api)('GET', "/api/v1/type-items/$id")->assertOk();

        ($this->api)('PATCH', "/api/v1/type-items/$id", ['name' => 'Renomme'])
            ->assertOk()->assertJsonPath('data.name', 'Renomme');

        ($this->api)('DELETE', "/api/v1/type-items/$id")->assertNoContent();

        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'entity_type' => 'type_item']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deleted']);
    });

    /**
     * Le meme code peut servir dans deux sources : ce qui distingue « STD » de
     * « STD », c'est la source dont il vient.
     */
    it('scopes the unique code to its source', function (): void {
        $package = Type::where('organization_id', $this->organization->id)->where('code', 'package')->firstOrFail();

        ($this->api)('POST', '/api/v1/type-items', [
            'typeId' => $this->vehicleSource->id, 'code' => 'STD', 'name' => 'Standard',
        ])->assertCreated();

        ($this->api)('POST', '/api/v1/type-items', [
            'typeId' => $package->id, 'code' => 'STD', 'name' => 'Standard',
        ])->assertCreated();

        ($this->api)('POST', '/api/v1/type-items', [
            'typeId' => $package->id, 'code' => 'STD', 'name' => 'Doublon',
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    });

    it('filters by source code and by status', function (): void {
        ($this->api)('POST', '/api/v1/type-items', [
            'typeId' => $this->vehicleSource->id, 'code' => 'FRIGO', 'name' => 'Frigorifique',
            'status' => 'inactive',
        ])->assertCreated();

        $vehicles = ($this->api)('GET', '/api/v1/type-items?type=vehicle')->assertOk()->json('data');
        expect($vehicles)->not->toBeEmpty();

        foreach ($vehicles as $item) {
            expect($item['typeCode'])->toBe('vehicle');
        }

        ($this->api)('GET', '/api/v1/type-items?type=vehicle&status=inactive')
            ->assertOk()->assertJsonCount(1, 'data');

        ($this->api)('GET', '/api/v1/type-items?search=Frigo')->assertOk()->assertJsonCount(1, 'data');
    });

    /**
     * Une valeur portee par un vehicule ne s'efface pas : la supprimer
     * laisserait `vehicles.vehicle_type_id` sans cible.
     */
    it('refuses to delete a value still in use', function (): void {
        $provider = Provider::factory()->forOrganization($this->organization)->create();
        $type = TypeItem::factory()->forOrganization($this->organization)->ofSystemType('vehicle')->create();
        Vehicle::factory()->forProvider($provider)->ofType($type)->create();

        ($this->api)('DELETE', "/api/v1/type-items/{$type->id}")->assertStatus(409);

        $this->assertDatabaseHas('type_items', ['id' => $type->id]);
    });

    /**
     * Depuis la fusion, « Palette » et « Camion 19T » vivent dans la meme
     * table : seul le controle de la source empeche d'affecter l'un pour
     * l'autre.
     */
    it('refuses a value taken from another source', function (): void {
        $packageType = TypeItem::factory()->forOrganization($this->organization)
            ->ofSystemType('package')->create();
        $provider = Provider::factory()->forOrganization($this->organization)->create();

        ($this->api)('POST', '/api/v1/vehicles', [
            'providerId' => $provider->id, 'vehicleTypeId' => $packageType->id,
            'code' => 'VEH-X', 'registrationNumber' => 'AA-000-BB',
            'payloadCapacity' => 1000, 'volumeCapacity' => 10, 'palletCapacity' => 4,
            'status' => 'active',
        ])->assertStatus(422)->assertJsonValidationErrors('vehicleTypeId');
    });

    it('hides the values of other organizations', function (): void {
        $foreign = TypeItem::factory()->ofSystemType('vehicle')->create();

        ($this->api)('GET', "/api/v1/type-items/{$foreign->id}")->assertNotFound();
    });
});
