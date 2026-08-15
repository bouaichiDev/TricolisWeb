<?php

declare(strict_types=1);

namespace App\Modules\Fleet\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'un type de véhicule.
 */
final readonly class UpdateVehicleTypeData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'code' => 'code',
        'name' => 'name',
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
