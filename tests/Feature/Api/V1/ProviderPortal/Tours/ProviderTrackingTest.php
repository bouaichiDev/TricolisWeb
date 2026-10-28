<?php

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Orders\Models\Order;
use App\Modules\ProviderPortal\Support\ProviderPortalRoles;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tracking\Models\TrackingEvent;
use Database\Seeders\DemoProviderPortalSeeder;
use Tests\Support\ProviderPortal;

/**
 * Chronologie d'une tournée : les horodatages réels de la tournée et de ses
 * arrêts, et les événements de suivi qui la concernent — rien d'inventé, et
 * jamais la note libre d'un événement.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    $this->alpha = ProviderPortal::provider('transports-alpha');
    $org = $this->alpha->organization;
    $this->tour = Tour::factory()->forOrganization($org)->create([
        'provider_id' => $this->alpha->id, 'status' => TourStatus::IN_PROGRESS, 'actual_start_at' => '2026-11-03 07:05:00',
    ]);
    $this->stop = TourStop::factory()->forTour($this->tour)->atSequence(1)->create([
        'status' => TourStopStatus::ARRIVED, 'actual_arrival_at' => '2026-11-03 08:10:00',
    ]);
    $order = Order::factory()->create(['organization_id' => $org->id]);
    TrackingEvent::factory()->forOrder($order)->create([
        'tour_id' => $this->tour->id, 'tour_stop_id' => $this->stop->id, 'event_type' => 'delivery_attempt',
        'description' => 'Note interne : client mécontent', 'latitude' => 46.2044, 'longitude' => 6.1432, 'occurred_at' => '2026-11-03 08:15:00',
    ]);
    $other = Tour::factory()->forOrganization($org)->create(['provider_id' => $this->alpha->id, 'status' => TourStatus::IN_PROGRESS]);
    TrackingEvent::factory()->forOrder($order)->create(['tour_id' => $other->id, 'event_type' => 'foreign_event', 'occurred_at' => '2026-11-03 09:00:00']);
    $this->admin = ProviderPortal::headers('admin@transports-alpha.test');
});

it('assemble la chronologie réelle de la tournée, dans l’ordre', function (): void {
    $entries = $this->withHeaders($this->admin)->getJson("/api/v1/provider/tours/{$this->tour->id}/tracking")->assertOk()->json('data');

    expect(collect($entries)->pluck('type')->all())->toBe(['tour_started', 'stop_arrived', 'event'])
        ->and($entries[1]['stopSequence'])->toBe(1)
        ->and($entries[2]['latitude'])->toBe(46.2044)
        ->and(json_encode($entries))->not->toContain('mécontent')->not->toContain('foreign_event');
});

it('répond 404 pour la tournée d’un autre fournisseur, 403 sans la permission', function (): void {
    $theirs = Tour::factory()->forOrganization($this->alpha->organization)->create(['provider_id' => ProviderPortal::provider('transports-beta')->id, 'status' => TourStatus::IN_PROGRESS]);
    $this->withHeaders($this->admin)->getJson("/api/v1/provider/tours/{$theirs->id}/tracking")->assertNotFound();

    Role::where('organization_id', $this->alpha->organization_id)->where('code', ProviderPortalRoles::VIEWER)->firstOrFail()
        ->permissions()->detach(Permission::where('code', 'provider.tracking.view')->value('id'));
    $this->withHeaders(ProviderPortal::headers('viewer@transports-alpha.test'))->getJson("/api/v1/provider/tours/{$this->tour->id}/tracking")->assertForbidden();
});

it('montre un arrêt passé à l’heure de son audit, sans en rendre le motif', function (): void {
    $this->withHeaders($this->admin)->postJson("/api/v1/provider/tours/{$this->tour->id}/stops/{$this->stop->id}/skip", ['reason' => 'Quai fermé, motif confidentiel'])->assertOk();

    $entries = $this->withHeaders($this->admin)->getJson("/api/v1/provider/tours/{$this->tour->id}/tracking")->assertOk()->json('data');

    expect(collect($entries)->firstWhere('type', 'stop_skipped'))->toMatchArray(['stopSequence' => 1])
        ->and(json_encode($entries))->not->toContain('confidentiel');
});
