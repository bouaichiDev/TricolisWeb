<?php

declare(strict_types=1);

namespace App\Modules\Stock\DTOs;

/**
 * Données de création d'un emplacement.
 *
 * `zoneCode`, `aisle`, `rack` et `level` sont des attributs libres : le §9
 * interdit une table `StockZone`.
 */
final readonly class CreateStockLocationData
{
    public function __construct(
        public string $depotId,
        public string $locationCode,
        public string $status,
        public ?string $parentLocationId = null,
        public ?string $zoneCode = null,
        public ?string $aisle = null,
        public ?string $rack = null,
        public ?string $level = null,
        public ?string $barcode = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            depotId: $validated['depotId'],
            locationCode: $validated['locationCode'],
            status: $validated['status'],
            parentLocationId: $validated['parentLocationId'] ?? null,
            zoneCode: $validated['zoneCode'] ?? null,
            aisle: $validated['aisle'] ?? null,
            rack: $validated['rack'] ?? null,
            level: $validated['level'] ?? null,
            barcode: $validated['barcode'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'depot_id' => $this->depotId,
            'parent_location_id' => $this->parentLocationId,
            'zone_code' => $this->zoneCode,
            'aisle' => $this->aisle,
            'rack' => $this->rack,
            'level' => $this->level,
            'location_code' => $this->locationCode,
            'barcode' => $this->barcode,
            'status' => $this->status,
        ];
    }
}
