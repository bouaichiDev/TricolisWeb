<?php

namespace Database\Factories\Modules\Catalogs\Models;

use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCatalogItem>
 */
class CustomerCatalogItemFactory extends Factory
{
    public function modelName(): string
    {
        return CustomerCatalogItem::class;
    }

    public function definition(): array
    {
        return [
            'catalog_id' => CustomerCatalog::factory(),
            'article_code' => fake()->unique()->bothify('ART-#####'),
            'barcode' => fake()->optional()->ean13(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'weight' => fake()->randomFloat(3, 0.1, 50),
            'volume' => fake()->randomFloat(4, 0.001, 2),
            'length' => fake()->randomFloat(3, 0.1, 2),
            'width' => fake()->randomFloat(3, 0.1, 2),
            'height' => fake()->randomFloat(3, 0.1, 2),
            'status' => 'active',
        ];
    }

    public function forCatalog(CustomerCatalog $catalog): static
    {
        return $this->state(fn (): array => ['catalog_id' => $catalog->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }
}
