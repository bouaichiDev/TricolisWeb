<?php

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Providers\Models\Provider;
use App\Modules\Tours\Models\Tour;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $this->payload = fn (array $o = []): array => array_merge([
        'tourNumber' => 'TRN-0001',
        'tourDate' => '2026-09-01',
        'agencyId' => $this->agency->id,
        'status' => 'draft',
    ], $o);
});

describe('tours creation', function (): void {
    it('creates a minimal tour', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.tourNumber', 'TRN-0001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.depotId', null)
            ->assertJsonPath('data.providerId', null);

        $this->assertDatabaseHas('tours', [
            'id' => $response->json('data.id'),
            'organization_id' => $this->organization->id,
        ]);
    });

    it('creates a tour with an optional depot', function (): void {
        $depot = Depot::factory()->create(['agency_id' => $this->agency->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)(['depotId' => $depot->id]))
            ->assertCreated()->assertJsonPath('data.depotId', $depot->id);
    });

    it('creates a tour with provider, driver and vehicle', function (): void {
        $provider = Provider::factory()->forOrganization($this->organization)->create();
        $driver = Driver::factory()->forProvider($provider)->create();
        $vehicle = Vehicle::factory()->forProvider($provider)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)([
                'providerId' => $provider->id,
                'driverId' => $driver->id,
                'vehicleId' => $vehicle->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.providerId', $provider->id)
            ->assertJsonPath('data.driverId', $driver->id);
    });

    it('starts every total at zero', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.totalPackages', 0)
            ->assertJsonPath('data.totalCustomers', 0)
            ->assertJsonPath('data.distanceMeters', 0);
    });
});

describe('tours reference constraints', function (): void {
    it('refuses an agency from another organization', function (): void {
        $foreign = Agency::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)(['agencyId' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('agencyId');
    });

    it('refuses a depot not attached to the agency', function (): void {
        $otherAgency = Agency::factory()->create(['organization_id' => $this->organization->id]);
        $depot = Depot::factory()->create(['agency_id' => $otherAgency->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)(['depotId' => $depot->id]))
            ->assertStatus(422)->assertJsonValidationErrors('depotId');
    });

    /**
     * Un identifiant venant d'une autre organisation ne doit pas seulement
     * exister : il doit exister *ici*. Sans cloisonnement, un simple ULID
     * deviné rattacherait le dépôt, le véhicule ou le chauffeur d'un
     * concurrent — et le confirmerait en répondant 201.
     */
    it('refuses a depot from another organization', function (): void {
        $foreign = Depot::factory()->create([
            'agency_id' => Agency::factory()->create([
                'organization_id' => Organization::factory()->create()->id,
            ])->id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)(['depotId' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('depotId');
    });

    it('refuses a vehicle from another organization', function (): void {
        $foreign = Vehicle::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)(['vehicleId' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('vehicleId');
    });

    it('refuses a driver from another organization', function (): void {
        $foreign = Driver::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)(['driverId' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('driverId');
    });

    it('refuses a provider from another organization', function (): void {
        $foreign = Provider::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)(['providerId' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('providerId');
    });

    it('refuses a driver not attached to the provider', function (): void {
        $provider = Provider::factory()->forOrganization($this->organization)->create();
        $otherProvider = Provider::factory()->forOrganization($this->organization)->create();
        $driver = Driver::factory()->forProvider($otherProvider)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)(['providerId' => $provider->id, 'driverId' => $driver->id]))
            ->assertStatus(422)->assertJsonValidationErrors('driverId');
    });

    it('refuses a vehicle not attached to the provider', function (): void {
        $provider = Provider::factory()->forOrganization($this->organization)->create();
        $otherProvider = Provider::factory()->forOrganization($this->organization)->create();
        $vehicle = Vehicle::factory()->forProvider($otherProvider)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)(['providerId' => $provider->id, 'vehicleId' => $vehicle->id]))
            ->assertStatus(422)->assertJsonValidationErrors('vehicleId');
    });
});

describe('tours validation', function (): void {
    it('refuses an end date before its start', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)([
                'plannedStartAt' => '2026-09-01T10:00:00Z',
                'plannedEndAt' => '2026-09-01T08:00:00Z',
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('plannedEndAt');
    });

    it('refuses negative durations', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)(['drivingTimeMinutes' => -5]))
            ->assertStatus(422)->assertJsonValidationErrors('drivingTimeMinutes');
    });

    it('refuses a status outside the enum', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)(['status' => 'archived']))
            ->assertStatus(422)->assertJsonValidationErrors('status');
    });

    it('refuses a duplicated tour number in the organization', function (): void {
        Tour::factory()->forAgency($this->agency)->create(['tour_number' => 'TRN-DUP']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)(['tourNumber' => 'TRN-DUP']))
            ->assertStatus(422)->assertJsonValidationErrors('tourNumber');
    });

    it('allows the same tour number in another organization', function (): void {
        $other = Organization::factory()->create();
        Tour::factory()->forOrganization($other)->create(['tour_number' => 'TRN-DUP']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)(['tourNumber' => 'TRN-DUP']))
            ->assertCreated();
    });
});

describe('tours read, update and delete', function (): void {
    it('reads, updates and deletes a tour', function (): void {
        $tour = Tour::factory()->forAgency($this->agency)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/tours/{$tour->id}")->assertOk()->assertJsonPath('data.id', $tour->id);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/tours/{$tour->id}", ['status' => 'planned'])
            ->assertOk()->assertJsonPath('data.status', 'planned');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/tours/{$tour->id}")->assertNoContent();

        $this->assertDatabaseMissing('tours', ['id' => $tour->id]);
    });

    /**
     * La modification passe par des règles `sometimes`, écrites à part : le
     * cloisonnement doit y être répété, faute de quoi ce qu'on refuse à la
     * création redevient acceptable par une simple mise à jour.
     */
    it('refuses foreign resources on update', function (): void {
        $tour = Tour::factory()->forAgency($this->agency)->create();
        $other = Organization::factory()->create();

        $foreign = [
            'depotId' => Depot::factory()->create([
                'agency_id' => Agency::factory()->create(['organization_id' => $other->id])->id,
            ])->id,
            'vehicleId' => Vehicle::factory()->create(['organization_id' => $other->id])->id,
            'driverId' => Driver::factory()->create(['organization_id' => $other->id])->id,
        ];

        foreach ($foreign as $field => $id) {
            $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
                ->patchJson("/api/v1/tours/{$tour->id}", [$field => $id])
                ->assertStatus(422)->assertJsonValidationErrors($field);
        }
    });

    it('hides a tour from another organization', function (): void {
        $foreign = Tour::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/tours/{$foreign->id}")->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/tours/{$foreign->id}", ['status' => 'planned'])->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/tours/{$foreign->id}")->assertNotFound();
    });
});

describe('tours list', function (): void {
    it('lists only the tours of the active organization', function (): void {
        Tour::factory(2)->forAgency($this->agency)->create();
        Tour::factory(3)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/tours')->assertOk()->assertJsonCount(2, 'data');
    });

    it('searches by tour number and instructions', function (): void {
        Tour::factory()->forAgency($this->agency)->create(['tour_number' => 'ZZZ-1', 'instructions' => 'Livraison matinale']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/tours?search=ZZZ')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/tours?search=matinale')->assertOk()->assertJsonCount(1, 'data');
    });

    it('filters by date range, agency and status', function (): void {
        Tour::factory()->forAgency($this->agency)->create(['tour_date' => '2026-09-10', 'status' => 'confirmed']);
        Tour::factory()->forAgency($this->agency)->create(['tour_date' => '2026-10-10']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/tours?tourDateFrom=2026-09-01&tourDateTo=2026-09-30')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/tours?status=confirmed')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/tours?agencyId={$this->agency->id}")->assertOk()->assertJsonCount(2, 'data');
    });

    it('paginates and rejects a forbidden sort column', function (): void {
        Tour::factory(5)->forAgency($this->agency)->create();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/tours?perPage=2')->assertOk()->assertJsonCount(2, 'data');
        expect($response->json('meta.perPage'))->toBe(2);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/tours?sort=organization_id')->assertStatus(422);
    });
});

describe('tours audit', function (): void {
    it('audits creation, update, status change and deletion', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', ($this->payload)())->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', ['action' => 'tour.created', 'entity_type' => 'tour', 'entity_id' => $id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/tours/$id", ['status' => 'planned'])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'tour.updated', 'entity_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tour.status_changed', 'entity_id' => $id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/tours/$id")->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'tour.deleted', 'entity_id' => $id]);
    });

    it('records only the changed fields on update', function (): void {
        $tour = Tour::factory()->forAgency($this->agency)->create(['tour_type' => 'distribution']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/tours/{$tour->id}", ['tourType' => 'collecte'])->assertOk();

        $log = AuditLog::where('action', 'tour.updated')->firstOrFail();
        expect($log->old_values)->toBe(['tour_type' => 'distribution'])
            ->and($log->new_values)->toBe(['tour_type' => 'collecte']);
    });
});
