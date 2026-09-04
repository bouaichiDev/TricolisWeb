<?php

use App\Modules\Providers\Models\Provider;
use App\Modules\Statuses\Models\Status;
use App\Modules\Types\Models\Type;
use App\Modules\Types\Models\TypeItem;

/**
 * Le statut écrit dans une table métier doit exister au référentiel.
 *
 * La colonne reste une chaîne — c'est la règle du projet — mais on n'y écrit
 * plus n'importe quoi : le code doit être décrit dans `statuses`, sous la
 * source de l'entité, et y être actif.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->api = fn (string $method, string $url, array $payload = []) => $this
        ->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->json($method, $url, $payload);
});

it('accepts a status defined for the entity', function (): void {
    ($this->api)('POST', '/api/v1/providers', [
        'code' => 'TRANS-OK', 'name' => 'Transports Atlas', 'status' => 'active',
    ])->assertCreated()->assertJsonPath('data.status', 'active');

    // La colonne reste textuelle : aucun `status_id` n'est stocke.
    $this->assertDatabaseHas('providers', ['code' => 'TRANS-OK', 'status' => 'active']);
});

it('refuses a status absent from the referential', function (): void {
    ($this->api)('POST', '/api/v1/providers', [
        'code' => 'TRANS-KO', 'name' => 'Transports Atlas', 'status' => 'invente',
    ])->assertStatus(422)->assertJsonValidationErrors('status');
});

/**
 * « active » existe pour un fournisseur et pour une commande, mais « draft »
 * n'existe que pour une commande : la source fait partie de la clé.
 */
it('refuses a status taken from another source', function (): void {
    expect(Status::where('source', 'order')->where('code', 'draft')->exists())->toBeTrue();

    ($this->api)('POST', '/api/v1/providers', [
        'code' => 'TRANS-SRC', 'name' => 'Transports Atlas', 'status' => 'draft',
    ])->assertStatus(422)->assertJsonValidationErrors('status');
});

it('refuses a deactivated status', function (): void {
    Status::where('source', 'provider')->where('code', 'inactive')->update(['active' => false]);

    ($this->api)('POST', '/api/v1/providers', [
        'code' => 'TRANS-OFF', 'name' => 'Transports Atlas', 'status' => 'inactive',
    ])->assertStatus(422)->assertJsonValidationErrors('status');
});

it('applies the same rule to drivers, vehicles and type items', function (): void {
    $provider = Provider::factory()->forOrganization($this->organization)->create();
    $vehicleType = TypeItem::factory()->forOrganization($this->organization)
        ->ofSystemType('vehicle')->create();
    $source = Type::where('organization_id', $this->organization->id)
        ->where('code', 'package')->firstOrFail();

    ($this->api)('POST', '/api/v1/drivers', [
        'providerId' => $provider->id, 'code' => 'DRV-KO', 'name' => 'Ali', 'status' => 'invente',
    ])->assertStatus(422)->assertJsonValidationErrors('status');

    ($this->api)('POST', '/api/v1/vehicles', [
        'providerId' => $provider->id, 'vehicleTypeId' => $vehicleType->id,
        'code' => 'VEH-KO', 'registrationNumber' => '11-AAA-22',
        'payloadCapacity' => 1000, 'volumeCapacity' => 10, 'palletCapacity' => 4,
        'status' => 'invente',
    ])->assertStatus(422)->assertJsonValidationErrors('status');

    ($this->api)('POST', '/api/v1/type-items', [
        'typeId' => $source->id, 'code' => 'BOX', 'name' => 'Carton', 'status' => 'invente',
    ])->assertStatus(422)->assertJsonValidationErrors('status');
});

/**
 * Une modification qui ne touche pas au statut n'a pas à le fournir : l'exiger
 * empêcherait de renommer un fournisseur dont le statut a été désactivé.
 */
it('leaves an untouched status alone on update', function (): void {
    $provider = Provider::factory()->forOrganization($this->organization)
        ->create(['status' => 'active']);

    Status::where('source', 'provider')->where('code', 'active')->update(['active' => false]);

    ($this->api)('PATCH', "/api/v1/providers/{$provider->id}", ['name' => 'Nouveau nom'])
        ->assertOk()->assertJsonPath('data.status', 'active');
});

it('finds no orphan status for the entities of this phase', function (): void {
    foreach (['provider', 'driver', 'vehicle', 'type', 'type_item'] as $source) {
        $this->artisan('tricolis:check-statuses', ['--source' => $source])->assertSuccessful();
    }
});
