<?php

declare(strict_types=1);

namespace App\Modules\Fleet\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'un véhicule.
 */
final readonly class UpdateVehicleData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'legacy_id' => 'legacyId',
        'provider_id' => 'providerId',
        'vehicle_type_id' => 'vehicleTypeId',
        'code' => 'code',
        'registration_number' => 'registrationNumber',
        'payload_capacity' => 'payloadCapacity',
        'volume_capacity' => 'volumeCapacity',
        'pallet_capacity' => 'palletCapacity',
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
