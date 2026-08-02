<?php

declare(strict_types=1);

namespace App\Modules\Stock\DTOs;

/**
 * Données de création d'un article de stock.
 *
 * Ni quantité ni emplacement : le stock réel vit dans `StockBalance`, et le §6
 * interdit de les porter ici.
 */
final readonly class CreateStockItemData
{
    public function __construct(
        public string $customerId,
        public string $articleCode,
        public string $status,
        public ?string $catalogItemId = null,
        public ?string $barcode = null,
        public ?string $description = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            customerId: $validated['customerId'],
            articleCode: $validated['articleCode'],
            status: $validated['status'],
            catalogItemId: $validated['catalogItemId'] ?? null,
            barcode: $validated['barcode'] ?? null,
            description: $validated['description'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'customer_id' => $this->customerId,
            'catalog_item_id' => $this->catalogItemId,
            'article_code' => $this->articleCode,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'status' => $this->status,
        ];
    }
}
