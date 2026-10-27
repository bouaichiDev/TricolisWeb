<?php

use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Organizations\Models\Organization;
use App\Modules\ProviderPortal\Support\ProviderPortalRoles;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Models\Tour;
use Database\Seeders\DemoProviderPortalSeeder;
use Tests\Support\ProviderPortal;

/**
 * Tableau de bord du portail fournisseur : des chiffres réels, et seulement
 * ceux du fournisseur courant, dans son organisation.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    $this->organization = authOrganization();
    $this->alpha = ProviderPortal::provider('transports-alpha');
    $this->beta = ProviderPortal::provider('transports-beta');
});

function tourFor(Organization $organization, string $providerId, TourStatus $status, ?string $date = null): Tour
{
    return Tour::factory()->forOrganization($organization)->create([
        'provider_id' => $providerId,
        'status' => $status,
        'tour_date' => $date ?? now($organization->timezone)->toDateString(),
    ]);
}

it('compte les tournées, chauffeurs et véhicules du seul fournisseur courant', function (): void {
    $yesterday = now($this->organization->timezone)->subDay()->toDateString();

    tourFor($this->organization, $this->alpha->id, TourStatus::PLANNED);
    tourFor($this->organization, $this->alpha->id, TourStatus::CONFIRMED);
    tourFor($this->organization, $this->alpha->id, TourStatus::IN_PROGRESS);
    tourFor($this->organization, $this->alpha->id, TourStatus::IN_PROGRESS, $yesterday);
    tourFor($this->organization, $this->alpha->id, TourStatus::COMPLETED);
    tourFor($this->organization, $this->alpha->id, TourStatus::COMPLETED, $yesterday);
    // Exclus : brouillon, annulée, autre fournisseur, autre organisation.
    tourFor($this->organization, $this->alpha->id, TourStatus::DRAFT);
    tourFor($this->organization, $this->alpha->id, TourStatus::CANCELLED);
    tourFor($this->organization, $this->beta->id, TourStatus::IN_PROGRESS);
    tourFor(Organization::factory()->create(), $this->alpha->id, TourStatus::IN_PROGRESS);

    Driver::factory()->count(2)->create(['organization_id' => $this->organization->id, 'provider_id' => $this->alpha->id, 'status' => 'active']);
    Driver::factory()->create(['organization_id' => $this->organization->id, 'provider_id' => $this->alpha->id, 'status' => 'inactive']);
    Driver::factory()->create(['organization_id' => $this->organization->id, 'provider_id' => $this->beta->id, 'status' => 'active']);
    Vehicle::factory()->create(['organization_id' => $this->organization->id, 'provider_id' => $this->alpha->id, 'status' => 'active']);
    Vehicle::factory()->create(['organization_id' => $this->organization->id, 'provider_id' => $this->alpha->id, 'status' => 'maintenance']);
    Vehicle::factory()->create(['organization_id' => $this->organization->id, 'provider_id' => $this->beta->id, 'status' => 'active']);

    $this->withHeaders(ProviderPortal::headers('admin@transports-alpha.test'))->getJson('/api/v1/provider/dashboard')
        ->assertOk()
        ->assertJsonPath('data', [
            'toursToday' => 4,
            'toursInProgress' => 2,
            'toursCompletedToday' => 1,
            'activeDrivers' => 2,
            'activeVehicles' => 1,
        ]);
});

it('ferme le tableau de bord sans `provider.dashboard.view`', function (): void {
    Role::where('organization_id', $this->organization->id)->where('code', ProviderPortalRoles::VIEWER)->firstOrFail()
        ->permissions()->detach(Permission::where('code', 'provider.dashboard.view')->value('id'));

    $this->withHeaders(ProviderPortal::headers('viewer@transports-alpha.test'))->getJson('/api/v1/provider/dashboard')->assertForbidden();
});
