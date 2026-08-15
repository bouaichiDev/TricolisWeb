<?php

namespace Database\Factories\Modules\Tours\Models;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Providers\Models\Provider;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tour>
 */
class TourFactory extends Factory
{
    public function modelName(): string
    {
        return Tour::class;
    }

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            // L'agence doit relever de la meme organisation que la tournee :
            // la deduire evite de produire un jeu que l'API refuserait.
            'agency_id' => fn (array $attributes): Agency => Agency::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ]),
            'depot_id' => null,
            'provider_id' => null,
            'vehicle_id' => null,
            'driver_id' => null,
            'tour_number' => fake()->unique()->bothify('TRN-#####'),
            'tour_date' => now()->toDateString(),
            'tour_type' => null,
            'instructions' => null,
            'status' => TourStatus::DRAFT,
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $organization->id,
            'agency_id' => Agency::factory()->state(['organization_id' => $organization->id]),
        ]);
    }

    public function forAgency(Agency $agency): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $agency->organization_id,
            'agency_id' => $agency->id,
        ]);
    }

    /**
     * Depot rattache a l'agence de la tournee, comme l'exige le §8.
     */
    public function withDepot(?Depot $depot = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'depot_id' => $depot?->id ?? Depot::factory()->state(['agency_id' => $attributes['agency_id']]),
        ]);
    }

    /**
     * Fournisseur, chauffeur et vehicule cohérents entre eux : un chauffeur
     * d'un autre fournisseur serait refusé par l'API.
     */
    public function withResources(?Provider $provider = null): static
    {
        return $this->state(function (array $attributes) use ($provider): array {
            $provider ??= Provider::factory()->create(['organization_id' => $attributes['organization_id']]);

            return [
                'provider_id' => $provider->id,
                'driver_id' => Driver::factory()->forProvider($provider),
                'vehicle_id' => Vehicle::factory()->forProvider($provider),
            ];
        });
    }

    public function status(TourStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
