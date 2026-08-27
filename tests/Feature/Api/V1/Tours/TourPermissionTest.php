<?php

use App\Modules\Agencies\Models\Agency;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourPeriod;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;

beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    // Membre sans aucune permission : ni owner, ni role porteur de droits.
    $this->membership = OrganizationUser::factory()->forOrganization($this->organization)->create(['is_owner' => false]);
    $this->powerless = $this->membership->user;

    $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $this->tour = Tour::factory()->forAgency($agency)->create();
    $this->stop = TourStop::factory()->forTour($this->tour)->atSequence(1)->create();
    $this->service = TourStopService::factory()->forStop($this->stop)->create(['sequence_within_stop' => 1]);
    $this->period = TourPeriod::factory()->forTour($this->tour)->atSequence(1)->create();

    $this->urls = [
        '/api/v1/tours',
        "/api/v1/tours/{$this->tour->id}/stops",
        "/api/v1/tours/{$this->tour->id}/stops/{$this->stop->id}/services",
        "/api/v1/tours/{$this->tour->id}/periods",
        "/api/v1/tours/{$this->tour->id}/periods/{$this->period->id}/assignments",
    ];
});

describe('missing permissions', function (): void {
    it('forbids reading each resource without the view permission', function (): void {
        foreach ($this->urls as $url) {
            $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
                ->getJson($url)->assertForbidden();
        }
    });

    it('forbids creating without the create permission', function (): void {
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tours', [
                'tourDate' => '2026-09-01',
                'agencyId' => $this->tour->agency_id, 'status' => 'draft',
            ])->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/tours/{$this->tour->id}/periods", [
                'periodType' => 'driving', 'sequence' => 9, 'status' => 'planned',
            ])->assertForbidden();
    });

    it('forbids updating and deleting without the matching permission', function (): void {
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/tours/{$this->tour->id}", ['status' => 'planned'])->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/tours/{$this->tour->id}/stops/{$this->stop->id}")->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/tours/{$this->tour->id}/periods/{$this->period->id}")->assertForbidden();
    });

    it('forbids reordering without the reorder permission', function (): void {
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/tours/{$this->tour->id}/stops/reorder", ['ids' => [$this->stop->id]])
            ->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/tours/{$this->tour->id}/periods/reorder", ['ids' => [$this->period->id]])
            ->assertForbidden();
    });

    it('grants access once the view permissions are attached to the role', function (): void {
        $role = Role::factory()->forOrganization($this->organization)->create();
        $permissions = Permission::whereIn('code', [
            'tours.view', 'tour_stops.view', 'tour_stop_services.view',
            'tour_periods.view', 'tour_period_assignments.view',
        ])->pluck('id');

        foreach ($permissions as $permissionId) {
            RolePermission::create(['role_id' => $role->id, 'permission_id' => $permissionId]);
        }

        UserRole::create(['organization_user_id' => $this->membership->id, 'role_id' => $role->id]);

        foreach ($this->urls as $url) {
            $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
                ->getJson($url)->assertOk();
        }

        // La lecture est ouverte, l'ecriture reste fermee.
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/tours/{$this->tour->id}")->assertForbidden();
    });

    it('requires the organization header on every planning route', function (): void {
        $user = authUser();

        foreach ($this->urls as $url) {
            $this->actingAs($user, 'sanctum')->getJson($url)->assertForbidden();
        }
    });

    it('rejects unauthenticated access', function (): void {
        $this->getJson('/api/v1/tours')->assertUnauthorized();
        $this->getJson("/api/v1/tours/{$this->tour->id}/stops")->assertUnauthorized();
    });
});
