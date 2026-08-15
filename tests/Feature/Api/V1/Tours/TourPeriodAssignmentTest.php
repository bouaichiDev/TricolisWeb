<?php

use App\Modules\Agencies\Models\Agency;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Packages\Models\Package;
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
    $this->period = TourPeriod::factory()->forTour($this->tour)->atSequence(1)->create();

    $this->order = Order::factory()->forOrganization($this->organization)->create();
    $this->orderService = OrderService::factory()->create(['order_id' => $this->order->id]);
    $this->service = TourStopService::factory()->forStop($this->stop)->create([
        'order_service_id' => $this->orderService->id,
        'sequence_within_stop' => 1,
    ]);

    $this->url = "/api/v1/tours/{$this->tour->id}/periods/{$this->period->id}/assignments";
});

describe('assignments creation', function (): void {
    it('creates an assignment without package', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ['tourStopServiceId' => $this->service->id])
            ->assertCreated()
            ->assertJsonPath('data.tourStopServiceId', $this->service->id)
            ->assertJsonPath('data.packageId', null);
    });

    it('creates an assignment with a package of the same order', function (): void {
        $package = Package::factory()->create(['order_id' => $this->order->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, [
                'tourStopServiceId' => $this->service->id,
                'packageId' => $package->id,
            ])
            ->assertCreated()->assertJsonPath('data.packageId', $package->id);
    });

    it('refuses a service belonging to another tour', function (): void {
        $otherTour = Tour::factory()->forAgency($this->agency)->create();
        $otherStop = TourStop::factory()->forTour($otherTour)->create();
        $foreignService = TourStopService::factory()->forStop($otherStop)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ['tourStopServiceId' => $foreignService->id])
            ->assertStatus(422)->assertJsonValidationErrors('tourStopServiceId');
    });

    it('refuses a package from another order', function (): void {
        $otherOrder = Order::factory()->forOrganization($this->organization)->create();
        $package = Package::factory()->create(['order_id' => $otherOrder->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, [
                'tourStopServiceId' => $this->service->id,
                'packageId' => $package->id,
            ])
            ->assertStatus(422)->assertJsonValidationErrors('packageId');
    });

    it('refuses a package from another organization', function (): void {
        $foreignPackage = Package::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, [
                'tourStopServiceId' => $this->service->id,
                'packageId' => $foreignPackage->id,
            ])
            ->assertStatus(422)->assertJsonValidationErrors('packageId');
    });

    it('refuses an exact duplicate', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ['tourStopServiceId' => $this->service->id])->assertCreated();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ['tourStopServiceId' => $this->service->id])
            ->assertStatus(422)->assertJsonValidationErrors('tourStopServiceId');
    });

    it('allows the same service twice with different packages', function (): void {
        $first = Package::factory()->create(['order_id' => $this->order->id]);
        $second = Package::factory()->create(['order_id' => $this->order->id]);

        foreach ([$first, $second] as $package) {
            $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
                ->postJson($this->url, [
                    'tourStopServiceId' => $this->service->id,
                    'packageId' => $package->id,
                ])->assertCreated();
        }

        expect(TourPeriodAssignment::where('tour_period_id', $this->period->id)->count())->toBe(2);
    });
});

describe('assignments scope', function (): void {
    it('hides an assignment of another period', function (): void {
        $otherPeriod = TourPeriod::factory()->forTour($this->tour)->atSequence(2)->create();
        $assignment = TourPeriodAssignment::factory()->linking($otherPeriod, $this->service)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("{$this->url}/{$assignment->id}")->assertNotFound();
    });

    it('hides the assignments of a tour from another organization', function (): void {
        $foreignTour = Tour::factory()->create();
        $foreignPeriod = TourPeriod::factory()->forTour($foreignTour)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/tours/{$foreignTour->id}/periods/{$foreignPeriod->id}/assignments")
            ->assertNotFound();
    });

    it('lists the assignments of the period', function (): void {
        TourPeriodAssignment::factory()->linking($this->period, $this->service)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson($this->url)->assertOk()->assertJsonCount(1, 'data');
    });
});

describe('assignments update and delete', function (): void {
    it('updates the package of an assignment', function (): void {
        $assignment = TourPeriodAssignment::factory()->linking($this->period, $this->service)->create();
        $package = Package::factory()->create(['order_id' => $this->order->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("{$this->url}/{$assignment->id}", ['packageId' => $package->id])
            ->assertOk()->assertJsonPath('data.packageId', $package->id);
    });

    it('refuses to move an assignment to a service of another tour', function (): void {
        $assignment = TourPeriodAssignment::factory()->linking($this->period, $this->service)->create();
        $otherTour = Tour::factory()->forAgency($this->agency)->create();
        $otherStop = TourStop::factory()->forTour($otherTour)->create();
        $foreignService = TourStopService::factory()->forStop($otherStop)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("{$this->url}/{$assignment->id}", ['tourStopServiceId' => $foreignService->id])
            ->assertStatus(422)->assertJsonValidationErrors('tourStopServiceId');
    });

    it('deletes an assignment', function (): void {
        $assignment = TourPeriodAssignment::factory()->linking($this->period, $this->service)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("{$this->url}/{$assignment->id}")->assertNoContent();

        $this->assertDatabaseMissing('tour_period_assignments', ['id' => $assignment->id]);
    });
});

describe('assignments audit', function (): void {
    it('audits creation, update and deletion', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ['tourStopServiceId' => $this->service->id])->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tour_period_assignment.created',
            'entity_type' => 'tour_period_assignment',
            'entity_id' => $id,
        ]);

        $package = Package::factory()->create(['order_id' => $this->order->id]);
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("{$this->url}/$id", ['packageId' => $package->id])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'tour_period_assignment.updated', 'entity_id' => $id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("{$this->url}/$id")->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'tour_period_assignment.deleted', 'entity_id' => $id]);
    });
});

describe('tour deletion cascade', function (): void {
    it('deletes the whole aggregate in the right order', function (): void {
        TourPeriodAssignment::factory()->linking($this->period, $this->service)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/tours/{$this->tour->id}")->assertNoContent();

        $this->assertDatabaseMissing('tours', ['id' => $this->tour->id]);
        $this->assertDatabaseMissing('tour_stops', ['tour_id' => $this->tour->id]);
        $this->assertDatabaseMissing('tour_periods', ['tour_id' => $this->tour->id]);
        $this->assertDatabaseMissing('tour_stop_services', ['tour_stop_id' => $this->stop->id]);
        $this->assertDatabaseMissing('tour_period_assignments', ['tour_period_id' => $this->period->id]);
    });
});
