<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Types\Models\TypeItem;
use Database\Seeders\DemoProviderPortalSeeder;
use Tests\Support\ProviderPortal;

/**
 * Affecter chauffeur et véhicule à une tournée attribuée : seulement ses
 * propres ressources, actives, libres sur le créneau, assez grandes, et sur
 * une tournée encore modifiable.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    $this->alpha = ProviderPortal::provider('transports-alpha');
    $beta = ProviderPortal::provider('transports-beta');
    $org = $this->alpha->organization;
    $type = TypeItem::where('organization_id', $org->id)->whereHas('type', fn ($q) => $q->where('code', 'vehicle'))->firstOrFail();
    $vehicle = fn ($provider, array $extra = []) => Vehicle::factory()->forProvider($provider)->create(['vehicle_type_id' => $type->id, 'payload_capacity' => 1000, 'volume_capacity' => 10, ...$extra]);
    $tour = fn (array $extra = []) => Tour::factory()->forOrganization($org)->create([
        'provider_id' => $this->alpha->id, 'status' => TourStatus::CONFIRMED, 'tour_date' => '2026-11-03',
        'planned_start_at' => '2026-11-03 07:00:00', 'planned_end_at' => '2026-11-03 12:00:00', 'total_weight' => 800, 'total_volume' => 8, ...$extra,
    ]);
    $this->driver = Driver::factory()->forProvider($this->alpha)->create();
    $this->vehicle = $vehicle($this->alpha);
    $this->busyDriver = Driver::factory()->forProvider($this->alpha)->create();
    $this->busyVehicle = $vehicle($this->alpha);
    $this->smallVehicle = $vehicle($this->alpha, ['payload_capacity' => 500]);
    $this->inactiveDriver = Driver::factory()->forProvider($this->alpha)->create(['status' => 'inactive']);
    $this->betaDriver = Driver::factory()->forProvider($beta)->create();
    $this->betaVehicle = $vehicle($beta);
    $this->carrierDriver = Driver::factory()->create(['organization_id' => $org->id, 'provider_id' => null]);
    $this->tour = $tour();
    $tour(['planned_start_at' => '2026-11-03 10:00:00', 'planned_end_at' => '2026-11-03 14:00:00', 'driver_id' => $this->busyDriver->id, 'vehicle_id' => $this->busyVehicle->id]);
    $this->betaTour = Tour::factory()->forOrganization($org)->create(['provider_id' => $beta->id, 'status' => TourStatus::CONFIRMED]);
    $this->admin = ProviderPortal::headers('admin@transports-alpha.test');
    $this->assign = fn (array $payload, ?Tour $target = null) => $this->withHeaders($this->admin)
        ->patchJson('/api/v1/provider/tours/'.($target ?? $this->tour)->id.'/resources', $payload);
});

it('affecte son chauffeur et son véhicule, et l’audite', function (): void {
    ($this->assign)(['driverId' => $this->driver->id, 'vehicleId' => $this->vehicle->id])
        ->assertOk()->assertJsonPath('data.driver.id', $this->driver->id)->assertJsonPath('data.vehicle.id', $this->vehicle->id);

    expect($this->tour->fresh()->driver_id)->toBe($this->driver->id)
        ->and(AuditLog::where('action', 'tour.updated')->where('entity_id', $this->tour->id)->exists())->toBeTrue();
});

it('refuse un chauffeur ou un véhicule qui n’est pas à lui, ou inactif', function (string $field, string $which): void {
    ($this->assign)([$field => $this->{$which}->id])->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($this->tour->fresh()->driver_id)->toBeNull()->and($this->tour->fresh()->vehicle_id)->toBeNull();
})->with([
    ['driverId', 'betaDriver'], ['driverId', 'carrierDriver'], ['driverId', 'inactiveDriver'], ['vehicleId', 'betaVehicle'],
]);

it('refuse un chauffeur ou un véhicule déjà pris sur un créneau qui se chevauche', function (string $field, string $which): void {
    ($this->assign)([$field => $this->{$which}->id])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([['driverId', 'busyDriver'], ['vehicleId', 'busyVehicle']]);

it('refuse un véhicule trop petit pour la tournée', function (): void {
    ($this->assign)(['vehicleId' => $this->smallVehicle->id])->assertUnprocessable()->assertJsonValidationErrors('vehicleId');
});

it('refuse une tournée qui n’est plus modifiable ou en cours de planification', function (string $case): void {
    $case === 'réservée' ? $this->tour->update(['locked_by' => authUser()->id, 'locked_at' => now()]) : $this->tour->update(['status' => TourStatus::from($case)]);

    ($this->assign)(['driverId' => $this->driver->id])->assertUnprocessable()->assertJsonValidationErrors('tour');
})->with(['in_progress', 'completed', 'cancelled', 'réservée']);

it('retire un chauffeur affecté', function (): void {
    $this->tour->update(['driver_id' => $this->driver->id]);

    ($this->assign)(['driverId' => null])->assertOk()->assertJsonPath('data.driver', null);
});

it('répond 404 pour la tournée d’un autre fournisseur, 403 sans la permission, 422 sur un champ imposé', function (): void {
    ($this->assign)(['driverId' => $this->driver->id], $this->betaTour)->assertNotFound();
    ($this->assign)(['driverId' => $this->driver->id, 'status' => 'completed'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->withHeaders(ProviderPortal::headers('viewer@transports-alpha.test'))
        ->patchJson("/api/v1/provider/tours/{$this->tour->id}/resources", ['driverId' => $this->driver->id])->assertForbidden();
});
