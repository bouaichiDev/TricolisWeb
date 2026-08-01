<?php

use App\Modules\Agencies\Models\Agency;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourPeriod;
use App\Modules\Tours\Models\TourPeriodAssignment;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $this->tour = Tour::factory()->forAgency($this->agency)->create();
    $this->stop = TourStop::factory()->forTour($this->tour)->atSequence(1)->create();

    $this->orderService = fn (): OrderService => OrderService::factory()->create([
        'order_id' => Order::factory()->forOrganization($this->organization),
    ]);

    $this->url = "/api/v1/tours/{$this->tour->id}/stops/{$this->stop->id}/services";
});

describe('tour stop services assignment', function (): void {
    it('assigns a service of the active organization', function (): void {
        $service = ($this->orderService)();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, [
                'orderServiceId' => $service->id,
                'sequenceWithinStop' => 1,
                'status' => 'planned',
            ])
            ->assertCreated()
            ->assertJsonPath('data.orderServiceId', $service->id)
            ->assertJsonPath('data.isActiveAssignment', true);
    });

    it('refuses a service from another organization', function (): void {
        $foreign = OrderService::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, [
                'orderServiceId' => $foreign->id,
                'sequenceWithinStop' => 1,
                'status' => 'planned',
            ])
            ->assertStatus(422)->assertJsonValidationErrors('orderServiceId');
    });

    it('refuses a duplicated sequence within the stop', function (): void {
        TourStopService::factory()->forStop($this->stop)->create(['sequence_within_stop' => 1]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, [
                'orderServiceId' => ($this->orderService)()->id,
                'sequenceWithinStop' => 1,
                'status' => 'planned',
            ])
            ->assertStatus(422)->assertJsonValidationErrors('sequenceWithinStop');
    });

    it('allows the same order service twice, to keep the history', function (): void {
        $service = ($this->orderService)();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ['orderServiceId' => $service->id, 'sequenceWithinStop' => 1, 'status' => 'planned'])
            ->assertCreated();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ['orderServiceId' => $service->id, 'sequenceWithinStop' => 2, 'status' => 'replanned'])
            ->assertCreated();
    });
});

describe('tour stop services activation', function (): void {
    it('deactivates a service without deleting it', function (): void {
        $first = TourStopService::factory()->forStop($this->stop)->create(['sequence_within_stop' => 1]);
        TourStopService::factory()->forStop($this->stop)->create(['sequence_within_stop' => 2]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("{$this->url}/{$first->id}", ['isActiveAssignment' => false])
            ->assertOk()->assertJsonPath('data.isActiveAssignment', false);

        $this->assertDatabaseHas('tour_stop_services', ['id' => $first->id, 'is_active_assignment' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tour_stop_service.deactivated', 'entity_id' => $first->id]);
    });

    it('refuses to deactivate the last active service of a stop', function (): void {
        $only = TourStopService::factory()->forStop($this->stop)->create(['sequence_within_stop' => 1]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("{$this->url}/{$only->id}", ['isActiveAssignment' => false])
            ->assertStatus(409);

        $this->assertDatabaseHas('tour_stop_services', ['id' => $only->id, 'is_active_assignment' => true]);
    });
});

describe('tour stop services deletion', function (): void {
    it('refuses to delete a service used by an assignment', function (): void {
        $first = TourStopService::factory()->forStop($this->stop)->create(['sequence_within_stop' => 1]);
        TourStopService::factory()->forStop($this->stop)->create(['sequence_within_stop' => 2]);
        $period = TourPeriod::factory()->forTour($this->tour)->create();
        TourPeriodAssignment::factory()->linking($period, $first)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("{$this->url}/{$first->id}")->assertStatus(409);

        $this->assertDatabaseHas('tour_stop_services', ['id' => $first->id]);
    });

    it('refuses to delete the last active service of a stop', function (): void {
        $only = TourStopService::factory()->forStop($this->stop)->create(['sequence_within_stop' => 1]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("{$this->url}/{$only->id}")->assertStatus(409);
    });

    it('deletes a service when another one stays active', function (): void {
        $first = TourStopService::factory()->forStop($this->stop)->create(['sequence_within_stop' => 1]);
        TourStopService::factory()->forStop($this->stop)->create(['sequence_within_stop' => 2]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("{$this->url}/{$first->id}")->assertNoContent();

        $this->assertDatabaseMissing('tour_stop_services', ['id' => $first->id]);
    });
});

describe('tour stop services scope', function (): void {
    it('hides a service of another stop', function (): void {
        $otherStop = TourStop::factory()->forTour($this->tour)->atSequence(2)->create();
        $service = TourStopService::factory()->forStop($otherStop)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("{$this->url}/{$service->id}")->assertNotFound();
    });

    it('hides the services of a tour from another organization', function (): void {
        $foreignTour = Tour::factory()->create();
        $foreignStop = TourStop::factory()->forTour($foreignTour)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/tours/{$foreignTour->id}/stops/{$foreignStop->id}/services")
            ->assertNotFound();
    });

    it('lists the services of the stop, active and historical', function (): void {
        TourStopService::factory()->forStop($this->stop)->create(['sequence_within_stop' => 1]);
        TourStopService::factory()->forStop($this->stop)->inactive()->create(['sequence_within_stop' => 2]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson($this->url)->assertOk()->assertJsonCount(2, 'data');
    });
});

describe('tour stop services reorder and audit', function (): void {
    it('rewrites the sequences within the stop', function (): void {
        $first = TourStopService::factory()->forStop($this->stop)->create(['sequence_within_stop' => 1]);
        $second = TourStopService::factory()->forStop($this->stop)->create(['sequence_within_stop' => 2]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("{$this->url}/reorder", ['ids' => [$second->id, $first->id]])
            ->assertNoContent();

        expect($second->fresh()->sequence_within_stop)->toBe(1)
            ->and($first->fresh()->sequence_within_stop)->toBe(2);

        $this->assertDatabaseHas('audit_logs', ['action' => 'tour_stop_service.reordered']);
    });

    it('audits creation and update', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, [
                'orderServiceId' => ($this->orderService)()->id,
                'sequenceWithinStop' => 1,
                'status' => 'planned',
            ])->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tour_stop_service.created',
            'entity_type' => 'tour_stop_service',
            'entity_id' => $id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("{$this->url}/$id", ['status' => 'done'])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'tour_stop_service.updated', 'entity_id' => $id]);
    });
});

describe('tour totals', function (): void {
    it('recomputes the tour totals from its active services', function (): void {
        $order = Order::factory()->forOrganization($this->organization)->create();
        $service = OrderService::factory()->create([
            'order_id' => $order->id,
            'weight' => 120.5,
            'volume' => 3.25,
            'package_count' => 4,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, [
                'orderServiceId' => $service->id,
                'sequenceWithinStop' => 1,
                'status' => 'planned',
            ])->assertCreated();

        $tour = $this->tour->fresh();

        expect((float) $tour->total_weight)->toBe(120.5)
            ->and($tour->total_packages)->toBe(4)
            ->and($tour->total_customers)->toBe(1);
    });

    it('excludes deactivated services from the totals', function (): void {
        $order = Order::factory()->forOrganization($this->organization)->create();
        $keep = OrderService::factory()->create(['order_id' => $order->id, 'package_count' => 2]);
        $drop = OrderService::factory()->create(['order_id' => $order->id, 'package_count' => 7]);

        foreach ([[$keep, 1], [$drop, 2]] as [$orderService, $sequence]) {
            $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
                ->postJson($this->url, [
                    'orderServiceId' => $orderService->id,
                    'sequenceWithinStop' => $sequence,
                    'status' => 'planned',
                ])->assertCreated();
        }

        $dropped = TourStopService::where('order_service_id', $drop->id)->firstOrFail();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("{$this->url}/{$dropped->id}", ['isActiveAssignment' => false])->assertOk();

        expect($this->tour->fresh()->total_packages)->toBe(2);
    });
});
