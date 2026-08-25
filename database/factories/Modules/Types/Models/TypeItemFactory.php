<?php

namespace Database\Factories\Modules\Types\Models;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Types\Models\Type;
use App\Modules\Types\Models\TypeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TypeItem>
 */
class TypeItemFactory extends Factory
{
    public function modelName(): string
    {
        return TypeItem::class;
    }

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'type_id' => fn (array $attributes): string => TypeFactory::system(
                'package',
                (string) $attributes['organization_id'],
            )->id,
            'code' => fake()->unique()->bothify('TI-####'),
            'name' => fake()->words(2, true),
            'status' => 'active',
            'position' => 0,
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }

    /** Valeur d'une source structurelle : `vehicle`, `package`, `grouping`. */
    public function ofSystemType(string $code): static
    {
        // Clôture imbriquée : un état s'évalue avant que l'organisation ne soit
        // créée, et `organization_id` porterait encore une fabrique.
        return $this->state(fn (): array => [
            'type_id' => fn (array $attributes): string => TypeFactory::system(
                $code,
                (string) $attributes['organization_id'],
            )->id,
        ]);
    }

    public function ofType(Type $type): static
    {
        return $this->state(fn (): array => [
            'type_id' => $type->id,
            'organization_id' => $type->organization_id,
        ]);
    }
}
