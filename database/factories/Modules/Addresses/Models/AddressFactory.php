<?php

namespace Database\Factories\Modules\Addresses\Models;

use App\Modules\Addresses\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    public function modelName(): string
    {
        return Address::class;
    }

    public function definition(): array
    {
        return [
            'code' => fake()->optional()->word(),
            'name' => fake()->optional()->company(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional()->secondaryAddress(),
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'country' => fake()->countryCode(),
            'latitude' => fake()->optional()->latitude(),
            'longitude' => fake()->optional()->longitude(),
            'is_default' => fake()->boolean(),
            'status' => 'active',
        ];
    }
}
