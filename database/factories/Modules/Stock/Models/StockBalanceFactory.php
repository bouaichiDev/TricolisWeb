<?php

namespace Database\Factories\Modules\Stock\Models;

use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockBalance>
 */
class StockBalanceFactory extends Factory
{
    public function modelName(): string
    {
        return StockBalance::class;
    }

    public function definition(): array
    {
        return [
            'stock_item_id' => StockItem::factory(),
            'stock_location_id' => StockLocation::factory(),
            'quantity' => 0,
            'reserved_quantity' => 0,
            'available_quantity' => 0,
            'updated_at' => now(),
        ];
    }

    /**
     * Solde coherent : available = quantity - reserved, invariant que les
     * Actions maintiennent.
     */
    public function withQuantity(float $quantity, float $reserved = 0): static
    {
        return $this->state(fn (): array => [
            'quantity' => $quantity,
            'reserved_quantity' => $reserved,
            'available_quantity' => $quantity - $reserved,
        ]);
    }

    public function at(StockItem $item, StockLocation $location): static
    {
        return $this->state(fn (): array => [
            'stock_item_id' => $item->id,
            'stock_location_id' => $location->id,
        ]);
    }
}
