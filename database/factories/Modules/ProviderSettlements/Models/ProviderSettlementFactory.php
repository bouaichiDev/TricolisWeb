<?php

namespace Database\Factories\Modules\ProviderSettlements\Models;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Providers\Models\Provider;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderSettlement>
 */
class ProviderSettlementFactory extends Factory
{
    public function modelName(): string
    {
        return ProviderSettlement::class;
    }

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'provider_id' => fn (array $attributes): Provider => Provider::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ]),
            'settlement_number' => fake()->unique()->bothify('STL-2026-#####'),
            'period_from' => null,
            'period_to' => null,
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
            'status' => 'draft',
        ];
    }

    public function forProvider(Provider $provider): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $provider->organization_id,
            'provider_id' => $provider->id,
        ]);
    }
}
