<?php

namespace Database\Factories\Modules\Drivers\Models;

use App\Modules\Addresses\Models\Address;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Organizations\Models\Organization;
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
            // L'organisation du chauffeur est toujours celle de son fournisseur :
            // la deduire evite de produire un jeu de donnees que l'API refuserait.
            'organization_id' => fn (array $attributes): string => Provider::findOrFail($attributes['provider_id'])->organization_id,
            'address_id' => null,
            'contact_id' => null,
            'code' => fake()->unique()->bothify('DRV-####'),
            'name' => fake()->name(),
            'status' => 'active',
        ];
    }

    public function forProvider(Provider $provider): static
    {
        return $this->state(fn (): array => [
            'provider_id' => $provider->id,
            'organization_id' => $provider->organization_id,
        ]);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => [
            'provider_id' => Provider::factory()->forOrganization($organization),
            'organization_id' => $organization->id,
        ]);
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
