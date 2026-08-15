<?php

namespace Database\Factories\Modules\Stock\Models;

use App\Modules\Orders\Models\OrderLine;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockLocation;
use App\Modules\Stock\Models\StockReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockReservation>
 */
class StockReservationFactory extends Factory
{
    public function modelName(): string
    {
        return StockReservation::class;
    }

    public function definition(): array
    {
        return [
            'stock_item_id' => StockItem::factory(),
            'stock_location_id' => StockLocation::factory(),
            'order_line_id' => OrderLine::factory(),
            'quantity' => 1,
            'status' => 'active',
            'reserved_at' => now(),
            // Active par defaut : liberee, elle ne serait plus liberable, ce qui
            // fausserait les jeux de test.
            'released_at' => null,
        ];
    }

    public function released(): static
    {
        return $this->state(fn (): array => [
            'released_at' => now(),
            'status' => 'released',
        ]);
    }
}
