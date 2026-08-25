<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Service;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Models\TrackingEventDefinition;
use App\Shared\Database\MorphMap;

/**
 * Le parcours client, publie par les changements de statut.
 *
 * Le chauffeur pose un statut, l'evenement apparait : personne ne le saisit.
 * Rien n'apparait si l'organisation n'a decrit aucune etape pour ce couple
 * (table, statut) — le parcours est une decision de configuration.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::where('organization_id', $this->organization->id)->firstOrFail();
    $this->agency = Agency::where('organization_id', $this->organization->id)->firstOrFail();

    $this->address = Address::factory()->create();
    EntityAddress::create([
        'organization_id' => $this->organization->id,
        'address_id' => $this->address->id,
        'entity_type' => MorphMap::ORGANIZATION,
        'entity_id' => $this->organization->id,
    ]);

    $this->service = Service::factory()->forOrganization($this->organization)->create();

    $this->define = fn (array $overrides = []): TrackingEventDefinition => TrackingEventDefinition::create([
        'organization_id' => $this->organization->id,
        'source_type' => MorphMap::ORDER_SERVICE,
        'status_code' => 'in_progress',
        'code' => 'loaded',
        'title' => 'Votre commande est chargée',
        'position' => 20,
        'active' => true,
        ...$overrides,
    ]);

    $this->createOrder = fn (string $serviceStatus = 'draft'): array => $this
        ->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson('/api/v1/orders', [
            'customerId' => $this->customer->id,
            'agencyId' => $this->agency->id,
            'orderDate' => now()->toISOString(),
            'lines' => [['name' => 'Canapé', 'quantity' => 1]],
            'services' => [[
                'serviceId' => $this->service->id, 'addressId' => $this->address->id,
                'serviceNumber' => 'SRV-1', 'sequence' => 1, 'requestedDate' => now()->toDateString(),
                'quantity' => 1, 'unit' => 'delivery', 'requiredTimeMinutes' => 30,
                'remainingTimeMinutes' => 30, 'weight' => 0, 'volume' => 0, 'packageCount' => 0,
                'customerUnitPrice' => 0, 'customerTotalPrice' => 0,
                'providerUnitCost' => 0, 'providerTotalCost' => 0, 'status' => $serviceStatus,
            ]],
        ])->assertCreated()->json('data');
});

it('publishes the step when a service reaches the configured status', function (): void {
    ($this->define)();
    $order = ($this->createOrder)();
    $serviceId = $order['services'][0]['id'];

    // Rien tant que le statut n'est pas atteint.
    $this->assertDatabaseMissing('tracking_events', ['order_id' => $order['id'], 'event_type' => 'loaded']);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->patchJson("/api/v1/orders/{$order['id']}/services/{$serviceId}/status", ['status' => 'in_progress'])
        ->assertOk();

    $this->assertDatabaseHas('tracking_events', [
        'order_id' => $order['id'],
        'order_service_id' => $serviceId,
        'event_type' => 'loaded',
        'status' => 'in_progress',
    ]);
});

/** Le parcours est une decision de configuration, pas un effet automatique. */
it('publishes nothing when no step describes the status', function (): void {
    $order = ($this->createOrder)();
    $serviceId = $order['services'][0]['id'];

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->patchJson("/api/v1/orders/{$order['id']}/services/{$serviceId}/status", ['status' => 'in_progress'])
        ->assertOk();

    $this->assertDatabaseCount('tracking_events', 0);
});

/** Une etape desactivee ne publie plus, sans etre supprimee. */
it('ignores an inactive definition', function (): void {
    ($this->define)(['active' => false]);
    $order = ($this->createOrder)();
    $serviceId = $order['services'][0]['id'];

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->patchJson("/api/v1/orders/{$order['id']}/services/{$serviceId}/status", ['status' => 'in_progress'])
        ->assertOk();

    $this->assertDatabaseCount('tracking_events', 0);
});

/**
 * Un aller-retour de statut ne reecrit pas l'histoire.
 *
 * Charge -> en preparation -> charge produirait deux etapes identiques : le
 * parcours dit ou on en est, pas combien de fois on y est passe.
 */
it('publishes a step only once per order', function (): void {
    ($this->define)();
    $order = ($this->createOrder)();
    $serviceId = $order['services'][0]['id'];

    foreach (['in_progress', 'pending', 'in_progress'] as $status) {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/orders/{$order['id']}/services/{$serviceId}/status", ['status' => $status]);
    }

    expect(TrackingEvent::where('order_id', $order['id'])
        ->where('event_type', 'loaded')->count())->toBe(1);
});

/** L'etape d'un autre organisme ne s'applique pas ici. */
it('never applies a definition from another organization', function (): void {
    $other = Organization::factory()->create();

    TrackingEventDefinition::create([
        'organization_id' => $other->id,
        'source_type' => MorphMap::ORDER_SERVICE,
        'status_code' => 'in_progress',
        'code' => 'loaded',
        'title' => 'Chargé ailleurs',
        'position' => 10,
    ]);

    $order = ($this->createOrder)();
    $serviceId = $order['services'][0]['id'];

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->patchJson("/api/v1/orders/{$order['id']}/services/{$serviceId}/status", ['status' => 'in_progress'])
        ->assertOk();

    $this->assertDatabaseCount('tracking_events', 0);
});
