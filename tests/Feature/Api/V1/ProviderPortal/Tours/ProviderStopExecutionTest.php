<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use Database\Seeders\DemoProviderPortalSeeder;
use Tests\Support\ProviderPortal;

/**
 * Faire avancer les arrêts d'une tournée en cours : arrivé, terminé, ou passé
 * avec un motif. Toujours dans le périmètre du fournisseur.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    $this->alpha = ProviderPortal::provider('transports-alpha');
    $this->tour = Tour::factory()->forOrganization($this->alpha->organization)->create(['provider_id' => $this->alpha->id, 'status' => TourStatus::IN_PROGRESS]);
    $this->stop = TourStop::factory()->forTour($this->tour)->create(['status' => TourStopStatus::PENDING]);
    $this->admin = ProviderPortal::headers('admin@transports-alpha.test');
    $this->post = fn (string $action, array $body = [], ?Tour $tour = null, ?TourStop $stop = null) => $this->withHeaders($this->admin)
        ->postJson('/api/v1/provider/tours/'.($tour ?? $this->tour)->id.'/stops/'.($stop ?? $this->stop)->id.'/'.$action, $body);
});

it('enregistre l’arrivée puis le départ, à l’heure du serveur', function (): void {
    ($this->post)('arrive')->assertOk()->assertJsonPath('data.status', 'arrived');
    ($this->post)('arrive')->assertOk();
    ($this->post)('complete')->assertOk()->assertJsonPath('data.status', 'completed');

    $stop = $this->stop->fresh();
    expect($stop->actual_arrival_at)->not->toBeNull()
        ->and($stop->actual_departure_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'tour_stop.updated')->where('entity_id', $this->stop->id)->count())->toBe(2);
});

it('refuse les transitions hors cycle', function (string $case): void {
    match ($case) {
        'tournée non démarrée' => $this->tour->update(['status' => TourStatus::CONFIRMED]),
        'terminer sans être arrivé' => null,
    };
    $action = $case === 'tournée non démarrée' ? 'arrive' : 'complete';

    ($this->post)($action)->assertUnprocessable()->assertJsonValidationErrors('status');
    expect($this->stop->fresh()->status)->toBe(TourStopStatus::PENDING);
})->with(['tournée non démarrée', 'terminer sans être arrivé']);

it('passe un arrêt avec un motif, consigné dans l’audit', function (): void {
    ($this->post)('skip', ['reason' => 'Client absent, portail fermé'])->assertOk()->assertJsonPath('data.status', 'skipped');

    $audit = AuditLog::where('action', 'tour_stop.skipped')->where('entity_id', $this->stop->id)->firstOrFail();
    expect(json_encode($audit->new_values))->toContain('Client absent');
});

it('exige un motif raisonnable pour passer un arrêt', function (mixed $reason): void {
    ($this->post)('skip', $reason === null ? [] : ['reason' => $reason])->assertUnprocessable()->assertJsonValidationErrors('reason');
})->with([null, 'ok', str_repeat('x', 501)]);

it('répond 404 pour un arrêt d’une autre tournée ou d’un autre fournisseur', function (): void {
    $otherTour = Tour::factory()->forOrganization($this->alpha->organization)->create(['provider_id' => $this->alpha->id, 'status' => TourStatus::IN_PROGRESS]);
    $theirs = Tour::factory()->forOrganization($this->alpha->organization)->create(['provider_id' => ProviderPortal::provider('transports-beta')->id, 'status' => TourStatus::IN_PROGRESS]);
    $theirStop = TourStop::factory()->forTour($theirs)->create(['status' => TourStopStatus::PENDING]);

    ($this->post)('arrive', [], $otherTour)->assertNotFound();
    ($this->post)('arrive', [], $theirs, $theirStop)->assertNotFound();
    expect($theirStop->fresh()->status)->toBe(TourStopStatus::PENDING);
});

it('termine ou passe un arrêt mis « en cours » par l’interne', function (string $action): void {
    $this->stop->update(['status' => TourStopStatus::IN_PROGRESS]);

    ($this->post)($action, $action === 'skip' ? ['reason' => 'Quai fermé'] : [])->assertOk();
})->with(['complete', 'skip']);

it('ne fait rien avancer pendant qu’un planificateur réserve la tournée', function (): void {
    $this->tour->update(['locked_by' => authUser()->id, 'locked_at' => now()]);

    ($this->post)('arrive')->assertUnprocessable()->assertJsonValidationErrors('status');
    expect($this->stop->fresh()->status)->toBe(TourStopStatus::PENDING);
});

it('ne réécrit pas le motif d’un arrêt déjà passé', function (): void {
    ($this->post)('skip', ['reason' => 'Quai fermé'])->assertOk();
    ($this->post)('skip', ['reason' => 'Autre motif'])->assertOk();

    expect(AuditLog::where('action', 'tour_stop.skipped')->where('entity_id', $this->stop->id)->count())->toBe(1);
});
