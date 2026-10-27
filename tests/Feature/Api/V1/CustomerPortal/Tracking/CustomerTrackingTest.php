<?php

use App\Modules\CustomerPortal\Support\CustomerPortalRoles;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Models\TrackingEventDefinition;
use App\Shared\Database\MorphMap;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Testing\TestResponse;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;
use Tests\Support\CustomerPortalTracking;

/**
 * Suivi authentifié d'une commande (§43).
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->ikea = CustomerPortalOrders::order('ikea');
    $this->qoqa = CustomerPortalOrders::order('qoqa');
});

function portalTracking(string $orderId, string $email = 'admin@ikea.test', string $suffix = ''): TestResponse
{
    return test()->withHeaders(CustomerPortal::headers($email))->getJson("/api/v1/customer/orders/{$orderId}/tracking{$suffix}");
}

it('tracks its own order', function (): void {
    CustomerPortalTracking::sharedTour();

    portalTracking($this->ikea->id)->assertOk()
        ->assertJsonPath('data.order.reference', $this->ikea->order_number)
        ->assertJsonPath('data.available', true)
        ->assertJsonStructure(['data' => ['order' => ['status'], 'progress' => ['percent'], 'services', 'timeline', 'map', 'eta', 'refreshSeconds']]);
});

it('answers 404 for the order of another customer', function (): void {
    portalTracking($this->qoqa->id)->assertNotFound();
    portalTracking($this->qoqa->id, suffix: '/services/'.$this->qoqa->orderServices()->value('id'))->assertNotFound();
});

it('refuses a member without the tracking permission', function (): void {
    CustomerPortalTracking::giveRole('viewer@ikea.test', CustomerPortalRoles::STOCK_MANAGER);

    portalTracking($this->ikea->id, 'viewer@ikea.test')->assertForbidden();
});

it('closes tracking when the carrier disabled it for the customer', function (): void {
    CustomerPortal::customer('ikea')->update(['tracking_enabled' => false]);

    portalTracking($this->ikea->id)->assertForbidden();
});

it('answers cleanly when nothing can be tracked yet', function (): void {
    // La commande de démonstration n'est ni planifiée ni commencée.
    portalTracking($this->ikea->id)->assertOk()
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.isActive', true)
        ->assertJsonPath('data.progress.completedServices', 0);
});

it('stops proposing a refresh once the order is settled', function (): void {
    $this->ikea->forceFill(['status' => OrderStatus::COMPLETED])->saveQuietly();

    portalTracking($this->ikea->id)->assertOk()
        ->assertJsonPath('data.isActive', false)
        ->assertJsonPath('data.refreshSeconds', null);
});

it('reports no ETA and no position for an order not on the road', function (): void {
    portalTracking($this->ikea->id)->assertOk()
        ->assertJsonPath('data.eta.available', false)
        ->assertJsonPath('data.eta.estimatedAt', null)
        ->assertJsonPath('data.map.available', false)
        ->assertJsonPath('data.map.currentPosition', null);
});

it('gives the planned arrival as ETA and the last position once the tour is on the road', function (): void {
    ['tour' => $tour, 'ikeaStop' => $stop] = CustomerPortalTracking::sharedTour();
    TrackingEvent::factory()->create([
        'organization_id' => $tour->organization_id, 'order_id' => $this->qoqa->id, 'tour_id' => $tour->id,
        'latitude' => 46.52, 'longitude' => 6.63, 'occurred_at' => now()->subMinutes(5),
    ]);

    $data = portalTracking($this->ikea->id)->assertOk()->json('data');

    expect($data['eta']['available'])->toBeTrue()
        ->and($data['eta']['estimatedAt'])->toBe($stop->planned_arrival_at->format('Y-m-d\TH:i:s'))
        ->and($data['map']['available'])->toBeTrue()
        ->and($data['map']['currentPosition'])->toMatchArray(['latitude' => 46.52, 'longitude' => 6.63])
        ->and($data['services'][0]['stop']['plannedArrivalAt'])->not->toBeNull();
});

it('hides the position before the tour leaves', function (): void {
    CustomerPortalTracking::sharedTour(TourStatus::PLANNED);

    portalTracking($this->ikea->id)->assertOk()
        ->assertJsonPath('data.map.available', false)
        ->assertJsonPath('data.eta.available', false);
});

it('builds a customer timeline from real data only', function (): void {
    $service = $this->ikea->orderServices()->first();
    TrackingEventDefinition::create([
        'organization_id' => $this->ikea->organization_id, 'source_type' => MorphMap::ORDER_SERVICE, 'status_code' => 'in_progress',
        'code' => 'on_the_way', 'title' => 'En route vers vous', 'position' => 1, 'active' => true, 'visible_to_customer' => true,
    ]);
    TrackingEventDefinition::create([
        'organization_id' => $this->ikea->organization_id, 'source_type' => MorphMap::ORDER_SERVICE, 'status_code' => 'planned',
        'code' => 'depot_loading', 'title' => 'Chargement interne', 'position' => 0, 'active' => true, 'visible_to_customer' => false,
    ]);
    TrackingEvent::factory()->create(['organization_id' => $this->ikea->organization_id, 'order_id' => $this->ikea->id, 'order_service_id' => $service->id, 'event_type' => 'on_the_way', 'occurred_at' => now()]);
    TrackingEvent::factory()->create(['organization_id' => $this->ikea->organization_id, 'order_id' => $this->ikea->id, 'order_service_id' => $service->id, 'event_type' => 'depot_loading', 'occurred_at' => now()]);
    ProofOfDelivery::factory()->forOrder($this->ikea)->create(['order_service_id' => $service->id, 'recipient_name' => 'Anna Berg']);

    $data = portalTracking($this->ikea->id)->json('data');
    $titles = collect($data['timeline'])->pluck('title');

    expect($titles)->toContain('Commande créée', 'En route vers vous', 'Preuve de livraison disponible')
        ->not->toContain('Chargement interne')
        ->and($data['services'][0]['progress'])->toMatchArray(['reached' => 1, 'total' => 1, 'percent' => 100])
        ->and($data['services'][0]['proofOfDelivery']['recipientName'])->toBe('Anna Berg');
});

it('tracks a single service of the order', function (): void {
    $serviceId = $this->ikea->orderServices()->value('id');

    portalTracking($this->ikea->id, suffix: "/services/{$serviceId}")->assertOk()
        ->assertJsonPath('data.service.id', $serviceId)
        ->assertJsonStructure(['data' => ['service' => ['status', 'slot', 'eta'], 'timeline']]);
    portalTracking($this->ikea->id, suffix: '/services/01J0000000000000000000000X')->assertNotFound();
});

it('exposes canTrack on the order detail', function (): void {
    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->getJson("/api/v1/customer/orders/{$this->ikea->id}")
        ->assertJsonPath('data.actions.canTrack', true);

    CustomerPortal::customer('ikea')->update(['tracking_enabled' => false]);

    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->getJson("/api/v1/customer/orders/{$this->ikea->id}")
        ->assertJsonPath('data.actions.canTrack', false);
});
