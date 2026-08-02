<?php

declare(strict_types=1);

namespace App\Modules\Stock\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'un emplacement.
 *
 * `depot_id` n'y figure pas : déplacer un emplacement d'un dépôt à l'autre
 * casserait la hiérarchie et l'unicité `(depot_id, location_code)`.
 * `parent_location_id` reste modifiable — c'est une réorganisation légitime —
 * mais passe par le contrôle de cycle.
 */
final readonly class UpdateStockLocationData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'parent_location_id' => 'parentLocationId',
        'zone_code' => 'zoneCode',
        'aisle' => 'aisle',
        'rack' => 'rack',
        'level' => 'level',
        'location_code' => 'locationCode',
        'barcode' => 'barcode',
        'status' => 'status',
    ];

    public function __construct(public PartialAttributes $attributes) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(PartialAttributes::fromValidated($validated, self::MAPPING));
    }
}
