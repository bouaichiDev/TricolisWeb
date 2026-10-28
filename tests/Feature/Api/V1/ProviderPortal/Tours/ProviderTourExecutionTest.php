<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use Database\Seeders\DemoProviderPortalSeeder;
use Tests\Support\ProviderPortal;

/**
 * Démarrer et terminer une tournée depuis le portail : cycle encadré, heure du
 * serveur, audit, et rejeu sans effet.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    $this->alpha = ProviderPortal::provider('transports-alpha');
    $this->tour = Tour::factory()->forOrganization($this->alpha->organization)->create([
        'provider_id' => $this->alpha->id, 'status' => TourStatus::CONFIRMED,
        'driver_id' => Driver::factory()->forProvider($this->alpha)->create()->id,
        'vehicle_id' => Vehicle::factory()->forProvider($this->alpha)->create()->id,
    ]);
    $this->admin = ProviderPortal::headers('admin@transports-alpha.test');
    $this->post = fn (string $action, ?Tour $tour = null, array $body = []) => $this->withHeaders($this->admin)
        ->postJson('/api/v1/provider/tours/'.($tour ?? $this->tour)->id.'/'.$action, $body);
    $this->statusAudits = fn () => AuditLog::where('action', 'tour.status_changed')->where('entity_id', $this->tour->id)->count();
});

it('démarre une tournée confirmée, à l’heure du serveur, une seule fois même rejouée', function (): void {
    ($this->post)('start')->assertOk()->assertJsonPath('data.status', 'in_progress');
    ($this->post)('start')->assertOk()->assertJsonPath('data.status', 'in_progress');

    expect($this->tour->fresh()->actual_start_at)->not->toBeNull()
        ->and(($this->statusAudits)())->toBe(1);
});

it('refuse de démarrer sans véhicule, ou une tournée qui n’est pas confirmée', function (string $case): void {
    $case === 'sans véhicule' ? $this->tour->update(['vehicle_id' => null]) : $this->tour->update(['status' => TourStatus::PLANNED]);

    ($this->post)('start')->assertUnprocessable()->assertJsonValidationErrors('status');
    expect($this->tour->fresh()->actual_start_at)->toBeNull();
})->with(['sans véhicule', 'planifiée']);

it('refuse une heure fournie par le client', function (): void {
    ($this->post)('start', body: ['actualStartAt' => '2020-01-01 00:00:00'])->assertUnprocessable()->assertJsonValidationErrors('actualStartAt');
});

it('ne termine qu’une fois tous les arrêts clos', function (): void {
    $this->tour->update(['status' => TourStatus::IN_PROGRESS, 'actual_start_at' => now()]);
    $stop = TourStop::factory()->forTour($this->tour)->create(['status' => TourStopStatus::ARRIVED]);
    TourStop::factory()->forTour($this->tour)->create(['status' => TourStopStatus::SKIPPED]);

    ($this->post)('complete')->assertUnprocessable()->assertJsonValidationErrors('status');

    $stop->update(['status' => TourStopStatus::COMPLETED]);
    ($this->post)('complete')->assertOk()->assertJsonPath('data.status', 'completed');
    expect($this->tour->fresh()->actual_end_at)->not->toBeNull();
});

it('répond 404 pour la tournée d’un autre fournisseur et 403 sans la permission', function (): void {
    $theirs = Tour::factory()->forOrganization($this->alpha->organization)->create(['provider_id' => ProviderPortal::provider('transports-beta')->id, 'status' => TourStatus::CONFIRMED]);

    ($this->post)('start', $theirs)->assertNotFound();
    $this->withHeaders(ProviderPortal::headers('viewer@transports-alpha.test'))->postJson("/api/v1/provider/tours/{$this->tour->id}/start")->assertForbidden();
    expect($theirs->fresh()->status)->toBe(TourStatus::CONFIRMED);
});

it('annonce les actions d’exécution que le serveur accepterait', function (): void {
    $stop = TourStop::factory()->forTour($this->tour)->create(['status' => TourStopStatus::PENDING]);
    $detail = fn () => $this->withHeaders($this->admin)->getJson("/api/v1/provider/tours/{$this->tour->id}")->assertOk();

    $detail()->assertJsonPath('data.actions.canStart', true)->assertJsonPath('data.actions.canComplete', false)
        ->assertJsonPath('data.stops.0.actions.canArrive', false);

    ($this->post)('start')->assertOk();

    $detail()->assertJsonPath('data.actions.canStart', false)->assertJsonPath('data.actions.canComplete', true)
        ->assertJsonPath('data.stops.0.actions.canArrive', true)->assertJsonPath('data.stops.0.actions.canSkip', true)
        ->assertJsonPath('data.stops.0.actions.canComplete', false);

    $this->withHeaders(ProviderPortal::headers('viewer@transports-alpha.test'))->getJson("/api/v1/provider/tours/{$this->tour->id}")
        ->assertJsonPath('data.actions.canComplete', false)->assertJsonPath('data.stops.0.actions.canArrive', false);
    expect($stop->exists)->toBeTrue();
});
