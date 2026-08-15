<?php

use App\Modules\Agencies\Models\Agency;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tracking\Models\TrackingEvent;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->order = Order::factory()->forOrganization($this->organization)->create();

    $this->payload = fn (array $o = []): array => array_merge([
        'orderId' => $this->order->id,
        'eventType' => 'pickup',
        'status' => 'done',
        'occurredAt' => '2026-09-01T08:00:00Z',
    ], $o);
});

describe('tracking events creation', function (): void {
    it('creates a minimal event and forces the organization of the order', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tracking-events', ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.eventType', 'pickup')
            ->assertJsonPath('data.organizationId', $this->organization->id);

        $this->assertDatabaseHas('tracking_events', [
            'id' => $response->json('data.id'),
            'organization_id' => $this->order->organization_id,
            'created_by' => $this->user->id,
        ]);
    });

    it('refuses an order from another organization', function (): void {
        $foreign = Order::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tracking-events', ($this->payload)(['orderId' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('orderId');
    });

    it('refuses a service belonging to another order', function (): void {
        $otherOrder = Order::factory()->forOrganization($this->organization)->create();
        $service = OrderService::factory()->create(['order_id' => $otherOrder->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tracking-events', ($this->payload)(['orderServiceId' => $service->id]))
            ->assertStatus(422)->assertJsonValidationErrors('orderServiceId');
    });

    it('refuses a tour from another organization', function (): void {
        $foreignTour = Tour::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tracking-events', ($this->payload)(['tourId' => $foreignTour->id]))
            ->assertStatus(422)->assertJsonValidationErrors('tourId');
    });

    it('refuses a stop that does not belong to the given tour', function (): void {
        $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
        $tour = Tour::factory()->forAgency($agency)->create();
        $otherTour = Tour::factory()->forAgency($agency)->create();
        $stop = TourStop::factory()->forTour($otherTour)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tracking-events', ($this->payload)([
                'tourId' => $tour->id,
                'tourStopId' => $stop->id,
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('tourStopId');
    });

    it('derives the tour when only the stop is given', function (): void {
        $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
        $tour = Tour::factory()->forAgency($agency)->create();
        $stop = TourStop::factory()->forTour($tour)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tracking-events', ($this->payload)(['tourStopId' => $stop->id]))
            ->assertCreated()
            ->assertJsonPath('data.tourId', $tour->id)
            ->assertJsonPath('data.tourStopId', $stop->id);
    });

    it('refuses a stop whose tour is outside the organization', function (): void {
        $foreignStop = TourStop::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tracking-events', ($this->payload)(['tourStopId' => $foreignStop->id]))
            ->assertStatus(422)->assertJsonValidationErrors('tourId');
    });
});

describe('tracking events validation', function (): void {
    it('refuses an out of range latitude', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tracking-events', ($this->payload)(['latitude' => 91]))
            ->assertStatus(422)->assertJsonValidationErrors('latitude');
    });

    it('refuses an out of range longitude', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tracking-events', ($this->payload)(['longitude' => -181]))
            ->assertStatus(422)->assertJsonValidationErrors('longitude');
    });

    it('accepts valid coordinates', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tracking-events', ($this->payload)([
                'latitude' => 33.5731,
                'longitude' => -7.5898,
            ]))
            ->assertCreated();
    });

    it('requires the order and a valid occurredAt', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tracking-events', ['eventType' => 'x', 'status' => 'y', 'occurredAt' => '2026-09-01'])
            ->assertStatus(422)->assertJsonValidationErrors('orderId');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tracking-events', ($this->payload)(['occurredAt' => 'pas-une-date']))
            ->assertStatus(422)->assertJsonValidationErrors('occurredAt');
    });
});

describe('tracking events immutability', function (): void {
    it('exposes no PATCH route', function (): void {
        $event = TrackingEvent::factory()->forOrder($this->order)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/tracking-events/{$event->id}", ['status' => 'modifie'])
            ->assertStatus(405);
    });

    it('exposes no DELETE route', function (): void {
        $event = TrackingEvent::factory()->forOrder($this->order)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/tracking-events/{$event->id}")
            ->assertStatus(405);

        $this->assertDatabaseHas('tracking_events', ['id' => $event->id]);
    });
});

describe('tracking events list', function (): void {
    it('lists only the events of the active organization', function (): void {
        TrackingEvent::factory(2)->forOrder($this->order)->create();
        TrackingEvent::factory(3)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/tracking-events')->assertOk()->assertJsonCount(2, 'data');
    });

    it('hides an event from another organization', function (): void {
        $foreign = TrackingEvent::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/tracking-events/{$foreign->id}")->assertNotFound();
    });

    it('searches and filters', function (): void {
        TrackingEvent::factory()->forOrder($this->order)->create([
            'event_type' => 'delivery',
            'status' => 'failed',
            'description' => 'Destinataire absent',
        ]);
        TrackingEvent::factory()->forOrder($this->order)->create(['event_type' => 'pickup']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/tracking-events?search=absent')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/tracking-events?eventType=delivery')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/tracking-events?status=failed')->assertOk()->assertJsonCount(1, 'data');
    });

    it('orders by occurredAt descending by default', function (): void {
        TrackingEvent::factory()->forOrder($this->order)->create(['occurred_at' => '2026-09-01 08:00:00']);
        TrackingEvent::factory()->forOrder($this->order)->create(['occurred_at' => '2026-09-05 08:00:00']);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/tracking-events')->assertOk();

        expect($response->json('data.0.occurredAt'))->toStartWith('2026-09-05');
    });

    it('rejects a forbidden sort column', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/tracking-events?sort=organization_id')->assertStatus(422);
    });
});

describe('tracking events nested reads', function (): void {
    it('lists the events of an order, a tour and a stop', function (): void {
        $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
        $tour = Tour::factory()->forAgency($agency)->create();
        $stop = TourStop::factory()->forTour($tour)->create();

        TrackingEvent::factory()->forOrder($this->order)->create([
            'tour_id' => $tour->id,
            'tour_stop_id' => $stop->id,
        ]);
        TrackingEvent::factory()->forOrder($this->order)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/orders/{$this->order->id}/tracking-events")->assertOk()->assertJsonCount(2, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/tours/{$tour->id}/tracking-events")->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/tours/{$tour->id}/stops/{$stop->id}/tracking-events")
            ->assertOk()->assertJsonCount(1, 'data');
    });

    it('hides the events of an order from another organization', function (): void {
        $foreignOrder = Order::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/orders/{$foreignOrder->id}/tracking-events")->assertNotFound();
    });
});

describe('tracking events audit', function (): void {
    it('audits creation only', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tracking-events', ($this->payload)())->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tracking_event.created',
            'entity_type' => 'tracking_event',
            'entity_id' => $response->json('data.id'),
        ]);
    });
});
