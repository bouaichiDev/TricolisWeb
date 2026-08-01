<?php

namespace Database\Factories\Modules\Providers\Models;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Providers\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    public function modelName(): string
    {
        return Provider::class;
    }

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'legacy_id' => null,
            'code' => fake()->unique()->bothify('PRV-####'),
            'name' => fake()->company(),
            // Valeurs plausibles, non normatives : le diagramme n'enumere pas
            // les types de fournisseur.
            'provider_type' => fake()->randomElement(['carrier', 'subcontractor', 'partner']),
            'status' => 'active',
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }

    public function withLegacyId(int $legacyId): static
    {
        return $this->state(fn (): array => ['legacy_id' => $legacyId]);
    }
}
