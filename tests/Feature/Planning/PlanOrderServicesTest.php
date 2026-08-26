<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Planning\Services\PlanningEligibility;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;
use App\Shared\Database\MorphMap;

/**
 * Glisser une commande dans une tournée.
 *
 * Un seul appel, une seule transaction : huit services ne doivent pas produire
 * huit requêtes dont la moitié échoue. Les éligibles passent, les autres sont
 * nommés avec leur motif — un service déjà livré ne doit pas empêcher de
 * planifier le reste de sa commande.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $this->tour = Tour::factory()->forAgency($this->agency)->create(['status' => 'draft']);

    $this->serviceAt = function (Address $address, string $status = 'ready_to_plan', array $overrides = []): OrderService {
        $order = Order::factory()->forOrganization($this->organization)->create();

        return OrderService::factory()->create(array_merge([
            'order_id' => $order->id,
            'address_id' => $address->id,
            'status' => $status,
        ], $overrides));
    };

    $this->plan = fn (array $payload) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->postJson("/api/v1/tours/{$this->tour->id}/plan", $payload);
});

it('plans every eligible service of an order in one call', function (): void {
    $address = Address::factory()->create();
    $order = Order::factory()->forOrganization($this->organization)->create();

    $services = OrderService::factory(3)->create([
        'order_id' => $order->id,
        'address_id' => $address->id,
        'status' => 'ready_to_plan',
    ]);

    $response = ($this->plan)(['orderIds' => [$order->id]])->assertOk();

    expect($response->json('data.planned'))->toHaveCount(3);
    expect($response->json('data.rejected'))->toBe([]);

    // Meme adresse, meme jour : un seul arret, trois services.
    expect(TourStop::where('tour_id', $this->tour->id)->count())->toBe(1);
    expect(TourStopService::whereIn('order_service_id', $services->pluck('id'))->count())->toBe(3);
});

/** Chaque service planifié passe en « planifiée » et porte une affectation active. */
it('marks the services planned and their assignment active', function (): void {
    $service = ($this->serviceAt)(Address::factory()->create());

    ($this->plan)(['orderServiceIds' => [$service->id]])->assertOk();

    expect($service->fresh()->status->value)->toBe('planned');
    $this->assertDatabaseHas('tour_stop_services', [
        'order_service_id' => $service->id,
        'is_active_assignment' => true,
        'status' => 'planned',
    ]);
});

/**
 * La règle retenue le 26 août 2026 : les éligibles entrent, les autres sont
 * nommés. Un service déjà livré ne bloque pas sa commande.
 */
it('plans what it can and names what it refuses', function (): void {
    $address = Address::factory()->create();
    $order = Order::factory()->forOrganization($this->organization)->create();

    $ready = OrderService::factory()->create([
        'order_id' => $order->id, 'address_id' => $address->id, 'status' => 'ready_to_plan',
    ]);
    $done = OrderService::factory()->create([
        'order_id' => $order->id, 'address_id' => $address->id, 'status' => 'completed',
    ]);

    $response = ($this->plan)(['orderIds' => [$order->id]])->assertOk();

    expect($response->json('data.planned'))->toBe([$ready->id]);
    expect($response->json('data.rejected'))->toBe([
        ['orderServiceId' => $done->id, 'reason' => PlanningEligibility::REASON_STATUS],
    ]);
});

/** Un service ne peut être actif que dans une tournée à la fois. */
it('refuses a service already assigned elsewhere', function (): void {
    $service = ($this->serviceAt)(Address::factory()->create());

    ($this->plan)(['orderServiceIds' => [$service->id]])->assertOk();

    $second = Tour::factory()->forAgency($this->agency)->create(['status' => 'draft']);

    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson("/api/v1/tours/{$second->id}/plan", ['orderServiceIds' => [$service->id]])
        ->assertOk();

    expect($response->json('data.rejected.0.reason'))->toBe(PlanningEligibility::REASON_ALREADY_ASSIGNED);
    expect(TourStop::where('tour_id', $second->id)->count())->toBe(0);
});

/** Deux adresses, deux arrêts : le camion se range à chaque lieu. */
it('creates one stop per address', function (): void {
    $first = ($this->serviceAt)(Address::factory()->create());
    $second = ($this->serviceAt)(Address::factory()->create());

    ($this->plan)(['orderServiceIds' => [$first->id, $second->id]])->assertOk();

    expect(TourStop::where('tour_id', $this->tour->id)->count())->toBe(2);
});

/**
 * Même adresse, créneaux qui ne se chevauchent pas : deux arrêts. Il faudra
 * bien revenir l'après-midi.
 */
it('splits a stop when the time windows cannot be served together', function (): void {
    $address = Address::factory()->create();

    $morning = ($this->serviceAt)($address, 'ready_to_plan', [
        'requested_from' => '2026-09-01 08:00:00', 'requested_to' => '2026-09-01 09:00:00',
    ]);
    $afternoon = ($this->serviceAt)($address, 'ready_to_plan', [
        'requested_from' => '2026-09-01 16:00:00', 'requested_to' => '2026-09-01 17:00:00',
    ]);

    ($this->plan)(['orderServiceIds' => [$morning->id, $afternoon->id]])->assertOk();

    expect(TourStop::where('tour_id', $this->tour->id)->count())->toBe(2);

    // Meme famille, deux arrets : la cle reunit le lieu et le jour, la
    // compatibilite decide du passage.
    expect(TourStop::where('tour_id', $this->tour->id)->pluck('grouping_key')->unique())->toHaveCount(1);
});

it('keeps overlapping windows on the same stop', function (): void {
    $address = Address::factory()->create();

    $early = ($this->serviceAt)($address, 'ready_to_plan', [
        'requested_from' => '2026-09-01 08:00:00', 'requested_to' => '2026-09-01 12:00:00',
    ]);
    $late = ($this->serviceAt)($address, 'ready_to_plan', [
        'requested_from' => '2026-09-01 11:00:00', 'requested_to' => '2026-09-01 14:00:00',
    ]);

    ($this->plan)(['orderServiceIds' => [$early->id, $late->id]])->assertOk();

    expect(TourStop::where('tour_id', $this->tour->id)->count())->toBe(1);
});

/**
 * Les chargements au dépôt ouvrent la tournée : on charge avant de partir,
 * quel que soit l'ordre dans lequel les commandes ont été glissées.
 */
it('puts the depot stop first, whatever the order of the drags', function (): void {
    $depotAddress = Address::factory()->create();
    $depot = Depot::factory()->create(['agency_id' => $this->agency->id]);

    EntityAddress::create([
        'organization_id' => $this->organization->id,
        'address_id' => $depotAddress->id,
        'entity_type' => MorphMap::DEPOT,
        'entity_id' => $depot->id,
        'is_default' => true,
    ]);

    $this->tour->forceFill(['depot_id' => $depot->id])->save();

    $client = ($this->serviceAt)(Address::factory()->create());
    ($this->plan)(['orderServiceIds' => [$client->id]])->assertOk();

    $loading = ($this->serviceAt)($depotAddress);
    ($this->plan)(['orderServiceIds' => [$loading->id]])->assertOk();

    $first = TourStop::where('tour_id', $this->tour->id)->orderBy('sequence')->first();

    expect($first->address_id)->toBe($depotAddress->id);
    expect(TourStop::where('tour_id', $this->tour->id)->orderBy('sequence')->pluck('sequence')->all())
        ->toBe([1, 2]);
});

it('recomputes the totals of the tour', function (): void {
    $service = ($this->serviceAt)(Address::factory()->create());

    ($this->plan)(['orderServiceIds' => [$service->id]])->assertOk();

    expect($this->tour->fresh()->total_customers)->toBe(1);
});

it('needs at least one order or one service', function (): void {
    ($this->plan)([])->assertStatus(422);
});

/** La réservation du brouillon vaut pour la planification comme pour le reste. */
it('refuses to plan into someone else’s draft', function (): void {
    $other = User::factory()->create();

    AuditLog::create([
        'organization_id' => $this->organization->id,
        'user_id' => $other->id,
        'action' => 'tour.created',
        'entity_type' => MorphMap::TOUR,
        'entity_id' => $this->tour->id,
        'created_at' => now(),
    ]);

    $service = ($this->serviceAt)(Address::factory()->create());

    ($this->plan)(['orderServiceIds' => [$service->id]])->assertForbidden();
    expect($service->fresh()->status->value)->toBe('ready_to_plan');
});
