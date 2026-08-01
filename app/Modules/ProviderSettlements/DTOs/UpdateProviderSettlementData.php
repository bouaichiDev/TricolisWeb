<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'un décompte.
 *
 * `tax_total` est modifiable — il est saisi, pas calculé — et son changement
 * entraîne le recalcul de `total`. `subtotal` et `total` ne le sont pas.
 */
final readonly class UpdateProviderSettlementData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'settlement_number' => 'settlementNumber',
        'period_from' => 'periodFrom',
        'period_to' => 'periodTo',
        'tax_total' => 'taxTotal',
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
