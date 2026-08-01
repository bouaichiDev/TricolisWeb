<?php

namespace Database\Factories\Modules\Providers\Models;

use App\Modules\Addresses\Models\Address;
use App\Modules\Contacts\Models\Contact;
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
            // Adresse et contact sont en `0..1` : sans eux par defaut.
            'address_id' => null,
            'contact_id' => null,
            'code' => fake()->unique()->bothify('PRV-####'),
            'name' => fake()->company(),
            'status' => 'active',
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }

    public function withAddress(?Address $address = null): static
    {
        return $this->state(fn (): array => ['address_id' => $address?->id ?? Address::factory()]);
    }

    public function withContact(?Contact $contact = null): static
    {
        return $this->state(fn (): array => ['contact_id' => $contact?->id ?? Contact::factory()]);
    }
}
