<?php

namespace Database\Factories\Modules\Drivers\Models;

use App\Modules\Drivers\Models\Driver;
use App\Modules\Identity\Models\User;
use App\Modules\Providers\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    public function modelName(): string
    {
        return Driver::class;
    }

    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'legacy_id' => null,
            // Un chauffeur n'a pas de compte par defaut : l'application
            // chauffeur est hors perimetre.
            'user_id' => null,
            'code' => fake()->unique()->bothify('DRV-####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('+2126########'),
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
        ];
    }

    public function forProvider(Provider $provider): static
    {
        return $this->state(fn (): array => ['provider_id' => $provider->id]);
    }

    public function withUser(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->id]);
    }
}
