<?php

namespace Database\Factories\Modules\ProviderSettlements\Models;

use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Modules\ProviderSettlements\Models\ProviderSettlementLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderSettlementLine>
 */
class ProviderSettlementLineFactory extends Factory
{
    public function modelName(): string
    {
        return ProviderSettlementLine::class;
    }

    public function definition(): array
    {
        return [
            'settlement_id' => ProviderSettlement::factory(),
            'order_service_id' => null,
            'description' => fake()->sentence(3),
            'quantity' => 1,
            'unit_cost' => 80,
            'total_cost' => 80,
        ];
    }

    public function forSettlement(ProviderSettlement $settlement): static
    {
        return $this->state(fn (): array => ['settlement_id' => $settlement->id]);
    }
}
