<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\DTOs;

/**
 * Données de création d'une ligne de décompte.
 *
 * `totalCost` n'y figure pas : il vaut `quantity × unitCost`, calculé par
 * `CalculateProviderSettlementLineTotal`.
 */
final readonly class CreateProviderSettlementLineData
{
    public function __construct(
        public string $description,
        public string $quantity,
        public string $unitCost,
        public ?string $orderServiceId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            description: $validated['description'],
            quantity: (string) $validated['quantity'],
            unitCost: (string) $validated['unitCost'],
            orderServiceId: $validated['orderServiceId'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $settlementId, string $totalCost): array
    {
        return [
            'settlement_id' => $settlementId,
            'order_service_id' => $this->orderServiceId,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unitCost,
            'total_cost' => $totalCost,
        ];
    }
}
