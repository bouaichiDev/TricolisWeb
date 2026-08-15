<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourPeriod;
use App\Modules\Tours\Models\TourStop;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $this->tour = Tour::factory()->forAgency($this->agency)->create();
    $this->address = Address::factory()->create();

    $this->orderService = fn (): OrderService => OrderService::factory()->create([
        'order_id' => Order::factory()->forOrganization($this->organization),
    ]);

    $this->payload = fn (array $o = [], ?OrderService $service = null): array => array_merge([
        'addressId' => $this->address->id,
        'sequence' => 1,
        'status' => 'pending',
        'services' => [[
            'orderServiceId' => ($service ?? ($this->orderService)())->id,
            'sequenceWithinStop' => 1,
            'status' => 'planned',
        ]],
    ], $o);
});

describe('tour stops creation', function (): void {
    it('creates a stop with at least one service', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/tours/{$this->tour->id}/stops", ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.sequence', 1)
            ->assertJsonCount(1, 'data.services');

        $this->assertDatabaseHas('tour_stops', ['id' => $response->json('data.id'), 'tour_id' => $this->tour->id]);
        $this->assertDatabaseHas('tour_stop_services', ['tour_stop_id' => $response->json('data.id')]);
    });

    it('refuses a stop without any service', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/tours/{$this->tour->id}/stops", ($this->payload)(['services' => []]))
            ->assertStatus(422)->assertJsonValidationErrors('services');
    });

    it('writes nothing when a service is out of scope', function (): void {
        $foreignService = OrderService::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/tours/{$this->tour->id}/stops", ($this->payload)([
                'services' => [[
                    'orderServiceId' => $foreignService->id,
                    'sequenceWithinStop' => 1,
                    'status' => 'planned',
                ]],
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('services.0.orderServiceId');

        // La creation est atomique : aucun arret orphelin ne subsiste.
        expect(TourStop::where('tour_id', $this->tour->id)->count())->toBe(0);
    });

    it('refuses a duplicated sequence in the tour', function (): void {
        TourStop::factory()->forTour($this->tour)->atSequence(1)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/tours/{$this->tour->id}/stops", ($this->payload)())
            ->assertStatus(422)->assertJsonValidationErrors('sequence');
    });

    it('refuses a departure before its arrival and negative minutes', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/tours/{$this->tour->id}/stops", ($this->payload)([
                'plannedArrivalAt' => '2026-09-01T10:00:00Z',
                'plannedDepartureAt' => '2026-09-01T09:00:00Z',
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('plannedDepartureAt');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/tours/{$this->tour->id}/stops", ($this->payload)(['waitingMinutes' => -1]))
            ->assertStatus(422)->assertJsonValidationErrors('waitingMinutes');
    });
});

describe('tour stops scope', function (): void {
    it('hides a stop of another tour', function (): void {
        $otherTour = Tour::factory()->forAgency($this->agency)->create();
        $stop = TourStop::factory()->forTour($otherTour)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/tours/{$this->tour->id}/stops/{$stop->id}")->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/tours/{$this->tour->id}/stops/{$stop->id}")->assertNotFound();
    });

    it('hides every stop of a tour from another organization', function (): void {
        $foreignTour = Tour::factory()->create();
        $stop = TourStop::factory()->forTour($foreignTour)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/tours/{$foreignTour->id}/stops")->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/tours/{$foreignTour->id}/stops/{$stop->id}")->assertNotFound();
    });

    it('lists the stops of the tour by sequence', function (): void {
        TourStop::factory()->forTour($this->tour)->atSequence(2)->create();
        TourStop::factory()->forTour($this->tour)->atSequence(1)->create();
        TourStop::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/tours/{$this->tour->id}/stops")->assertOk()->assertJsonCount(2, 'data');

        expect($response->json('data.0.sequence'))->toBe(1);
    });
});

describe('tour stops update and delete', function (): void {
    it('updates a stop', function (): void {
        $stop = TourStop::factory()->forTour($this->tour)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/tours/{$this->tour->id}/stops/{$stop->id}", ['status' => 'arrived'])
            ->assertOk()->assertJsonPath('data.status', 'arrived');
    });

    it('deletes a stop and its services', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/tours/{$this->tour->id}/stops", ($this->payload)())->assertCreated();
        $stopId = $response->json('data.id');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/tours/{$this->tour->id}/stops/$stopId")->assertNoContent();

        $this->assertDatabaseMissing('tour_stops', ['id' => $stopId]);
        $this->assertDatabaseMissing('tour_stop_services', ['tour_stop_id' => $stopId]);
    });

    it('refuses to delete a stop still referenced by a period', function (): void {
        $stop = TourStop::factory()->forTour($this->tour)->create();
        TourPeriod::factory()->forStop($stop)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/tours/{$this->tour->id}/stops/{$stop->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('tour_stops', ['id' => $stop->id]);
    });
});

describe('tour stops reorder', function (): void {
    it('rewrites sequences from one to N', function (): void {
        $first = TourStop::factory()->forTour($this->tour)->atSequence(1)->create();
        $second = TourStop::factory()->forTour($this->tour)->atSequence(2)->create();
        $third = TourStop::factory()->forTour($this->tour)->atSequence(3)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/tours/{$this->tour->id}/stops/reorder", [
                'ids' => [$third->id, $first->id, $second->id],
            ])->assertNoContent();

        expect($third->fresh()->sequence)->toBe(1)
            ->and($first->fresh()->sequence)->toBe(2)
            ->and($second->fresh()->sequence)->toBe(3);
    });

    it('refuses a partial list', function (): void {
        $first = TourStop::factory()->forTour($this->tour)->atSequence(1)->create();
        TourStop::factory()->forTour($this->tour)->atSequence(2)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/tours/{$this->tour->id}/stops/reorder", ['ids' => [$first->id]])
            ->assertStatus(422)->assertJsonValidationErrors('ids');
    });

    it('refuses a duplicated identifier', function (): void {
        $first = TourStop::factory()->forTour($this->tour)->atSequence(1)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/tours/{$this->tour->id}/stops/reorder", ['ids' => [$first->id, $first->id]])
            ->assertStatus(422);
    });

    it('audits the reorder', function (): void {
        $stop = TourStop::factory()->forTour($this->tour)->atSequence(1)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/tours/{$this->tour->id}/stops/reorder", ['ids' => [$stop->id]])
            ->assertNoContent();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tour_stop.reordered',
            'entity_type' => 'tour',
            'entity_id' => $this->tour->id,
        ]);
    });
});

describe('tour stops audit', function (): void {
    it('audits creation, update and deletion', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/tours/{$this->tour->id}/stops", ($this->payload)())->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', ['action' => 'tour_stop.created', 'entity_type' => 'tour_stop', 'entity_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tour_stop_service.created']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/tours/{$this->tour->id}/stops/$id", ['status' => 'completed'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'tour_stop.updated', 'entity_id' => $id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/tours/{$this->tour->id}/stops/$id")->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'tour_stop.deleted', 'entity_id' => $id]);
    });
});
