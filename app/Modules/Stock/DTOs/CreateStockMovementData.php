<?php

declare(strict_types=1);

namespace App\Modules\Stock\DTOs;

/**
 * Données de création d'un mouvement de stock.
 *
 * Les deux emplacements sont facultatifs — une entrée n'a pas de source, une
 * sortie pas de destination — mais l'Action exige qu'au moins l'un soit fourni.
 *
 * `createdBy` n'est pas dans le payload : c'est l'utilisateur authentifié, ou
 * `null` pour un mouvement produit par un automate.
 */
final readonly class CreateStockMovementData
{
    public function __construct(
        public string $stockItemId,
        public string $movementType,
        public string $quantity,
        public ?string $sourceLocationId = null,
        public ?string $destinationLocationId = null,
        public ?string $sourceEntityType = null,
        public ?string $sourceEntityId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            stockItemId: $validated['stockItemId'],
            movementType: $validated['movementType'],
            quantity: (string) $validated['quantity'],
            sourceLocationId: $validated['sourceLocationId'] ?? null,
            destinationLocationId: $validated['destinationLocationId'] ?? null,
            sourceEntityType: $validated['sourceEntityType'] ?? null,
            sourceEntityId: $validated['sourceEntityId'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(?string $createdBy, string $now): array
    {
        return [
            'stock_item_id' => $this->stockItemId,
            'source_location_id' => $this->sourceLocationId,
            'destination_location_id' => $this->destinationLocationId,
            'movement_type' => $this->movementType,
            'quantity' => $this->quantity,
            'source_entity_type' => $this->sourceEntityType,
            'source_entity_id' => $this->sourceEntityId,
            'created_by' => $createdBy,
            'created_at' => $now,
        ];
    }
}
