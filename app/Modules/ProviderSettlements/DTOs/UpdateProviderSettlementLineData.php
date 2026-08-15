<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'une ligne de décompte.
 */
final readonly class UpdateProviderSettlementLineData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'order_service_id' => 'orderServiceId',
        'description' => 'description',
        'quantity' => 'quantity',
        'unit_cost' => 'unitCost',
    ];

    /** Colonnes dont la modification impose un recalcul du total. */
    public const array RECALCULATES = ['quantity', 'unit_cost'];

    public function __construct(public PartialAttributes $attributes) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(PartialAttributes::fromValidated($validated, self::MAPPING));
    }
}
