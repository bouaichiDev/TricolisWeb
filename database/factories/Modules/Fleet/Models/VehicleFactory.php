<?php

namespace Database\Factories\Modules\Fleet\Models;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Providers\Models\Provider;
use App\Modules\Types\Models\TypeItem;
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

    /**
     * L'organisation vient en premier, le reste s'y accroche.
     *
     * Depuis que le fournisseur est facultatif, c'est le véhicule qui porte son
     * organisation. Fournisseur et type sont donc dérivés d'elle : un véhicule
     * dont les trois divergent serait un jeu de données que l'API refuserait.
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'provider_id' => fn (array $attributes): string => Provider::factory()
                ->create(['organization_id' => $attributes['organization_id']])->id,
            'vehicle_type_id' => fn (array $attributes): string => TypeItem::factory()
                ->state(['organization_id' => $attributes['organization_id']])
                ->ofSystemType('vehicle')->create()->id,
            'code' => fake()->unique()->bothify('VEH-####'),
            'registration_number' => fake()->unique()->bothify('##-?????-##'),
            'payload_capacity' => fake()->randomFloat(3, 500, 25000),
            'volume_capacity' => fake()->randomFloat(4, 5, 90),
            'pallet_capacity' => fake()->numberBetween(2, 33),
            'status' => 'active',
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }

    public function forProvider(Provider $provider): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $provider->organization_id,
            'provider_id' => $provider->id,
        ]);
    }

    /** Véhicule du transporteur lui-même, sans fournisseur. */
    public function withoutProvider(): static
    {
        return $this->state(fn (): array => ['provider_id' => null]);
    }

    public function ofType(TypeItem $type): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $type->organization_id,
            'vehicle_type_id' => $type->id,
        ]);
    }
}
