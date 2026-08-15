<?php

namespace Database\Factories\Modules\Stock\Models;

use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockLocation;
use App\Modules\Stock\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function modelName(): string
    {
        return StockMovement::class;
    }

    public function definition(): array
    {
        return [
            'stock_item_id' => StockItem::factory(),
            // Une entree par defaut : pas de source, une destination.
            'source_location_id' => null,
            'destination_location_id' => StockLocation::factory(),
            'movement_type' => 'inbound',
            'quantity' => 10,
            'source_entity_type' => null,
            'source_entity_id' => null,
            'created_by' => null,
            'created_at' => now(),
        ];
    }

    public function forItem(StockItem $item): static
    {
        return $this->state(fn (): array => ['stock_item_id' => $item->id]);
    }

    public function inbound(StockLocation $destination): static
    {
        return $this->state(fn (): array => [
            'source_location_id' => null,
            'destination_location_id' => $destination->id,
        ]);
    }

    public function outbound(StockLocation $source): static
    {
        return $this->state(fn (): array => [
            'source_location_id' => $source->id,
            'destination_location_id' => null,
            'movement_type' => 'outbound',
        ]);
    }
}
