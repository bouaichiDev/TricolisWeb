<?php

namespace Database\Factories\Modules\Contacts\Models;

use App\Modules\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    public function modelName(): string
    {
        return Contact::class;
    }

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->optional()->phoneNumber(),
            'mobile' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->email(),
            'preferred_language' => 'fr',
            'is_active' => true,
        ];
    }
}
