<?php

declare(strict_types=1);

namespace App\Modules\Stock\DTOs;

/**
 * Données de création d'une réservation.
 *
 * `releasedAt` n'y figure pas : une réservation naît active, la libération
 * passe par `POST /stock-reservations/{id}/release`.
 */
final readonly class CreateStockReservationData
{
    public function __construct(
        public string $stockItemId,
        public string $stockLocationId,
        public string $orderLineId,
        public string $quantity,
        public string $status,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            stockItemId: $validated['stockItemId'],
            stockLocationId: $validated['stockLocationId'],
            orderLineId: $validated['orderLineId'],
            quantity: (string) $validated['quantity'],
            status: $validated['status'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $now): array
    {
        return [
            'stock_item_id' => $this->stockItemId,
            'stock_location_id' => $this->stockLocationId,
            'order_line_id' => $this->orderLineId,
            'quantity' => $this->quantity,
            'status' => $this->status,
            'reserved_at' => $now,
        ];
    }
}
