<?php

use App\Modules\Agencies\Models\Agency;
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
    $this->url = "/api/v1/tours/{$this->tour->id}/periods";

    $this->payload = fn (array $o = []): array => array_merge([
        'periodType' => 'driving',
        'sequence' => 1,
        'status' => 'planned',
    ], $o);
});

describe('tour periods creation', function (): void {
    it('creates a period attached to the tour', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.periodType', 'driving')
            ->assertJsonPath('data.tourStopId', null);

        $this->assertDatabaseHas('tour_periods', [
            'id' => $response->json('data.id'),
            'tour_id' => $this->tour->id,
        ]);
    });

    it('creates a period attached to a stop of the tour', function (): void {
        $stop = TourStop::factory()->forTour($this->tour)->atSequence(1)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)(['tourStopId' => $stop->id]))
            ->assertCreated()->assertJsonPath('data.tourStopId', $stop->id);
    });

    it('refuses a stop belonging to another tour', function (): void {
        $otherTour = Tour::factory()->forAgency($this->agency)->create();
        $stop = TourStop::factory()->forTour($otherTour)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)(['tourStopId' => $stop->id]))
            ->assertStatus(422)->assertJsonValidationErrors('tourStopId');
    });

    it('refuses a duplicated sequence in the tour', function (): void {
        TourPeriod::factory()->forTour($this->tour)->atSequence(1)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)())
            ->assertStatus(422)->assertJsonValidationErrors('sequence');
    });

    it('refuses inconsistent dates and negative values', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)([
                'plannedStartAt' => '2026-09-01T12:00:00Z',
                'plannedEndAt' => '2026-09-01T11:00:00Z',
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('plannedEndAt');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)(['breakMinutes' => -1]))
            ->assertStatus(422)->assertJsonValidationErrors('breakMinutes');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)(['distanceMeters' => -100]))
            ->assertStatus(422)->assertJsonValidationErrors('distanceMeters');
    });
});

describe('tour periods list and scope', function (): void {
    it('filters by stop, type and planned range', function (): void {
        $stop = TourStop::factory()->forTour($this->tour)->atSequence(1)->create();
        TourPeriod::factory()->forStop($stop)->atSequence(1)->create([
            'period_type' => 'service',
            'planned_start_at' => '2026-09-01 08:00:00',
        ]);
        TourPeriod::factory()->forTour($this->tour)->atSequence(2)->create([
            'period_type' => 'driving',
            'planned_start_at' => '2026-09-05 08:00:00',
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("{$this->url}?tourStopId={$stop->id}")->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("{$this->url}?periodType=driving")->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("{$this->url}?plannedFrom=2026-09-03T00:00:00Z")->assertOk()->assertJsonCount(1, 'data');
    });

    it('hides a period of another tour', function (): void {
        $otherTour = Tour::factory()->forAgency($this->agency)->create();
        $period = TourPeriod::factory()->forTour($otherTour)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("{$this->url}/{$period->id}")->assertNotFound();
    });

    it('hides the periods of a tour from another organization', function (): void {
        $foreignTour = Tour::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/tours/{$foreignTour->id}/periods")->assertNotFound();
    });

    it('rejects a forbidden sort column', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("{$this->url}?sort=tour_id")->assertStatus(422);
    });
});

describe('tour periods update and delete', function (): void {
    it('updates a period', function (): void {
        $period = TourPeriod::factory()->forTour($this->tour)->atSequence(1)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("{$this->url}/{$period->id}", ['status' => 'done', 'distanceMeters' => 4200])
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.distanceMeters', 4200);
    });

    it('refuses to move a period to a stop of another tour', function (): void {
        $period = TourPeriod::factory()->forTour($this->tour)->atSequence(1)->create();
        $foreignStop = TourStop::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("{$this->url}/{$period->id}", ['tourStopId' => $foreignStop->id])
            ->assertStatus(422)->assertJsonValidationErrors('tourStopId');
    });

    it('deletes a period without assignment', function (): void {
        $period = TourPeriod::factory()->forTour($this->tour)->atSequence(1)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("{$this->url}/{$period->id}")->assertNoContent();

        $this->assertDatabaseMissing('tour_periods', ['id' => $period->id]);
    });

    it('refuses to delete a period carrying assignments', function (): void {
        $stop = TourStop::factory()->forTour($this->tour)->atSequence(1)->create();
        $service = TourStopService::factory()->forStop($stop)->create();
        $period = TourPeriod::factory()->forTour($this->tour)->atSequence(1)->create();
        TourPeriodAssignment::factory()->linking($period, $service)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("{$this->url}/{$period->id}")->assertStatus(409);

        $this->assertDatabaseHas('tour_periods', ['id' => $period->id]);
    });
});

describe('tour periods reorder and totals', function (): void {
    it('rewrites the sequences', function (): void {
        $first = TourPeriod::factory()->forTour($this->tour)->atSequence(1)->create();
        $second = TourPeriod::factory()->forTour($this->tour)->atSequence(2)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("{$this->url}/reorder", ['ids' => [$second->id, $first->id]])
            ->assertNoContent();

        expect($second->fresh()->sequence)->toBe(1)->and($first->fresh()->sequence)->toBe(2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tour_period.reordered']);
    });

    it('sums the period distances into the tour', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)(['distanceMeters' => 1500]))->assertCreated();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)(['sequence' => 2, 'distanceMeters' => 2500]))->assertCreated();

        expect($this->tour->fresh()->distance_meters)->toBe(4000);
    });

    it('never recomputes driving and working time', function (): void {
        $tour = Tour::factory()->forAgency($this->agency)->create([
            'driving_time_minutes' => 90,
            'working_time_minutes' => 120,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/tours/{$tour->id}/periods", ($this->payload)(['distanceMeters' => 500]))
            ->assertCreated();

        // Le diagramme n'enumere pas les valeurs de periodType : rien ne permet
        // de distinguer conduite et service, les deux champs restent saisis.
        expect($tour->fresh()->driving_time_minutes)->toBe(90)
            ->and($tour->fresh()->working_time_minutes)->toBe(120);
    });
});

describe('tour periods audit', function (): void {
    it('audits creation, update and deletion', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)())->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', ['action' => 'tour_period.created', 'entity_type' => 'tour_period', 'entity_id' => $id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("{$this->url}/$id", ['status' => 'done'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'tour_period.updated', 'entity_id' => $id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("{$this->url}/$id")->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'tour_period.deleted', 'entity_id' => $id]);
    });
});
