<?php

namespace Database\Factories\Modules\Types\Models;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Types\Models\Type;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Type>
 */
class TypeFactory extends Factory
{
    public function modelName(): string
    {
        return Type::class;
    }

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => fake()->unique()->bothify('type-????'),
            'name' => fake()->words(2, true),
            'status' => 'active',
            'is_system' => false,
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }

    /**
     * Une des trois sources structurelles, créée une seule fois par organisation.
     *
     * `firstOrCreate` plutôt qu'une insertion : la contrainte d'unicité porte
     * sur le couple organisation-code, et deux véhicules d'un même jeu de test
     * demanderaient sinon deux fois la source `vehicle`.
     */
    public static function system(string $code, string $organizationId): Type
    {
        return Type::firstOrCreate(
            ['organization_id' => $organizationId, 'code' => $code],
            ['name' => ucfirst($code), 'status' => 'active', 'is_system' => true],
        );
    }
}
