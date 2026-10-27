<?php

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Types\Models\TypeItem;
use Database\Seeders\DemoProviderPortalSeeder;
use Tests\Support\ProviderPortal;

/**
 * Véhicules du portail fournisseur : même isolation que les chauffeurs, et
 * un type de véhicule toujours pris dans le référentiel de l'organisation.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    $this->alpha = ProviderPortal::provider('transports-alpha');
    $this->beta = ProviderPortal::provider('transports-beta');
    $this->type = TypeItem::where('organization_id', $this->alpha->organization_id)->whereHas('type', fn ($q) => $q->where('code', 'vehicle'))->firstOrFail();
    $this->mine = Vehicle::factory()->forProvider($this->alpha)->create(['vehicle_type_id' => $this->type->id, 'code' => 'VA-01']);
    $this->theirs = Vehicle::factory()->forProvider($this->beta)->create(['vehicle_type_id' => $this->type->id, 'code' => 'VB-01']);
    $this->carrier = Vehicle::factory()->forOrganization($this->alpha->organization)->create(['provider_id' => null, 'vehicle_type_id' => $this->type->id, 'code' => 'VT-01']);
    $this->admin = ProviderPortal::headers('admin@transports-alpha.test');
    $this->payload = [
        'code' => 'VA-02', 'registrationNumber' => 'GE-12345', 'vehicleTypeId' => $this->type->id,
        'payloadCapacity' => 1200.5, 'volumeCapacity' => 12.25, 'palletCapacity' => 4,
    ];
});

it('ne liste que les véhicules du fournisseur courant, et les types de son organisation', function (): void {
    expect(collect($this->withHeaders($this->admin)->getJson('/api/v1/provider/vehicles')->assertOk()->json('data'))->pluck('code')->all())->toBe(['VA-01']);

    $foreignType = TypeItem::factory()->state(['organization_id' => Organization::factory()->create()->id])->ofSystemType('vehicle')->create();
    $types = collect($this->withHeaders($this->admin)->getJson('/api/v1/provider/vehicle-types')->assertOk()->json('data'))->pluck('id');

    expect($types)->toContain($this->type->id)->not->toContain($foreignType->id);
});

it('répond 404 pour un véhicule étranger ou du transporteur', function (string $who): void {
    $id = $who === 'autre fournisseur' ? $this->theirs->id : $this->carrier->id;

    $this->withHeaders($this->admin)->getJson("/api/v1/provider/vehicles/{$id}")->assertNotFound();
    $this->withHeaders($this->admin)->patchJson("/api/v1/provider/vehicles/{$id}", ['code' => 'VOLE'])->assertNotFound();
    $this->withHeaders($this->admin)->postJson("/api/v1/provider/vehicles/{$id}/deactivate")->assertNotFound();
})->with(['autre fournisseur', 'transporteur']);

it('crée un véhicule rattaché au fournisseur courant', function (): void {
    $this->withHeaders($this->admin)->postJson('/api/v1/provider/vehicles', $this->payload)
        ->assertCreated()->assertJsonPath('data.code', 'VA-02')->assertJsonPath('data.vehicleType.id', $this->type->id);

    expect(Vehicle::where('code', 'VA-02')->value('provider_id'))->toBe($this->alpha->id);
});

it('refuse un type d’une autre organisation ou d’un autre référentiel', function (string $case): void {
    $type = $case === 'autre organisation'
        ? TypeItem::factory()->state(['organization_id' => Organization::factory()->create()->id])->ofSystemType('vehicle')->create()
        : TypeItem::factory()->state(['organization_id' => $this->alpha->organization_id])->ofSystemType('package')->create();

    $this->withHeaders($this->admin)->postJson('/api/v1/provider/vehicles', [...$this->payload, 'vehicleTypeId' => $type->id])
        ->assertUnprocessable()->assertJsonValidationErrors('vehicleTypeId');
})->with(['autre organisation', 'autre référentiel']);

it('refuse des capacités négatives ou démesurées', function (string $field, mixed $value): void {
    $this->withHeaders($this->admin)->postJson('/api/v1/provider/vehicles', [...$this->payload, $field => $value])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([['payloadCapacity', -1], ['volumeCapacity', 'beaucoup'], ['palletCapacity', 10001], ['payloadCapacity', 1e12]]);

it('refuse une immatriculation déjà prise sans dire par qui, et les champs imposés', function (): void {
    $this->withHeaders($this->admin)->postJson('/api/v1/provider/vehicles', [...$this->payload, 'registrationNumber' => $this->theirs->registration_number])
        ->assertUnprocessable()->assertJsonPath('errors.registrationNumber.0', 'Cette immatriculation ne peut pas être utilisée.');
    $this->withHeaders($this->admin)->postJson('/api/v1/provider/vehicles', [...$this->payload, 'providerId' => $this->beta->id, 'status' => 'active'])
        ->assertUnprocessable()->assertJsonValidationErrors(['providerId', 'status']);
});

it('refuse de désactiver un véhicule engagé, puis l’accepte une fois la tournée terminée', function (): void {
    $tour = Tour::factory()->forOrganization($this->alpha->organization)->create(['provider_id' => $this->alpha->id, 'vehicle_id' => $this->mine->id, 'status' => TourStatus::CONFIRMED]);

    $this->withHeaders($this->admin)->postJson("/api/v1/provider/vehicles/{$this->mine->id}/deactivate")->assertUnprocessable();
    $tour->update(['status' => TourStatus::COMPLETED]);
    $this->withHeaders($this->admin)->postJson("/api/v1/provider/vehicles/{$this->mine->id}/deactivate")->assertOk()->assertJsonPath('data.status', 'inactive');
});
