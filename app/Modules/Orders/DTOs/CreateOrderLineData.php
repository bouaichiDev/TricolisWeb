<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTOs;

/**
 * Ligne de commande à créer, manuelle ou issue d'un catalogue.
 */
final readonly class CreateOrderLineData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(
        public ?string $catalogItemId,
        public array $attributes,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            $input['catalogItemId'] ?? null,
            array_filter([
                'external_reference' => $input['externalReference'] ?? null,
                'article_code' => $input['articleCode'] ?? null,
                'barcode' => $input['barcode'] ?? null,
                'name' => $input['name'] ?? null,
                'description' => $input['description'] ?? null,
                'quantity' => $input['quantity'] ?? null,
                'weight' => $input['weight'] ?? null,
                'volume' => $input['volume'] ?? null,
                'length' => $input['length'] ?? null,
                'width' => $input['width'] ?? null,
                'height' => $input['height'] ?? null,
                'purchase_price' => $input['purchasePrice'] ?? null,
                'selling_price' => $input['sellingPrice'] ?? null,
                'status' => $input['status'] ?? null,
            ], static fn ($value): bool => $value !== null),
        );
    }

    public function comesFromCatalog(): bool
    {
        return $this->catalogItemId !== null;
    }
}
