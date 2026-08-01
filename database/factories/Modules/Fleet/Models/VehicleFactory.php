<?php

namespace Database\Factories\Modules\Fleet\Models;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Providers\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    public function modelName(): string
    {
        return Vehicle::class;
    }

    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'vehicle_type_id' => VehicleType::factory(),
            'legacy_id' => null,
            'code' => fake()->unique()->bothify('VEH-####'),
            'registration_number' => fake()->unique()->bothify('##-?????-##'),
            'payload_capacity' => fake()->randomFloat(3, 500, 25000),
            'volume_capacity' => fake()->randomFloat(4, 5, 90),
            'pallet_capacity' => fake()->numberBetween(2, 33),
            'status' => 'active',
        ];
    }

    /**
     * Fournisseur et type dans la même organisation : un véhicule dont les deux
     * divergent serait un jeu de données invalide, refusé par l'API.
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => [
            'provider_id' => Provider::factory()->forOrganization($organization),
            'vehicle_type_id' => VehicleType::factory()->forOrganization($organization),
        ]);
    }

    public function forProvider(Provider $provider): static
    {
        return $this->state(fn (): array => [
            'provider_id' => $provider->id,
            'vehicle_type_id' => VehicleType::factory()->state(['organization_id' => $provider->organization_id]),
        ]);
    }

    public function ofType(VehicleType $type): static
    {
        return $this->state(fn (): array => ['vehicle_type_id' => $type->id]);
    }
}
