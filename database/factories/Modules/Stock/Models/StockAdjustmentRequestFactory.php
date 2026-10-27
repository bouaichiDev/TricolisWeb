<?php

namespace Database\Factories\Modules\Stock\Models;

use App\Modules\Stock\Enums\StockAdjustmentRequestStatus;
use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Modules\Stock\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustmentRequest>
 */
class StockAdjustmentRequestFactory extends Factory
{
    public function modelName(): string
    {
        return StockAdjustmentRequest::class;
    }

    public function definition(): array
    {
        return [
            // Client et organisation suivent l'article : une demande ne vise
            // jamais l'article d'un autre client.
            'stock_item_id' => StockItem::factory(),
            'customer_id' => fn (array $attributes): string => StockItem::findOrFail($attributes['stock_item_id'])->customer_id,
            'organization_id' => fn (array $attributes): string => StockItem::findOrFail($attributes['stock_item_id'])->customer->organization_id,
            'stock_location_id' => null,
            'displayed_quantity' => '120.000',
            'requested_quantity' => '115.000',
            'reason' => 'Écart constaté à l’inventaire',
            'comment' => null,
            'status' => StockAdjustmentRequestStatus::PENDING,
        ];
    }

    public function forItem(StockItem $item): static
    {
        return $this->state(fn (): array => ['stock_item_id' => $item->id]);
    }
}
