<?php

declare(strict_types=1);

namespace App\Modules\Fleet\DTOs;

/**
 * Données de création d'un véhicule.
 */
final readonly class CreateVehicleData
{
    public function __construct(
        public ?string $providerId,
        public string $vehicleTypeId,
        public string $code,
        public string $registrationNumber,
        public string $payloadCapacity,
        public string $volumeCapacity,
        public int $palletCapacity,
        public string $status,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            providerId: $validated['providerId'] ?? null,
            vehicleTypeId: $validated['vehicleTypeId'],
            code: $validated['code'],
            registrationNumber: $validated['registrationNumber'],
            payloadCapacity: (string) $validated['payloadCapacity'],
            volumeCapacity: (string) $validated['volumeCapacity'],
            palletCapacity: (int) $validated['palletCapacity'],
            status: $validated['status'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $organizationId): array
    {
        return [
            'organization_id' => $organizationId,
            'provider_id' => $this->providerId,
            'vehicle_type_id' => $this->vehicleTypeId,
            'code' => $this->code,
            'registration_number' => $this->registrationNumber,
            'payload_capacity' => $this->payloadCapacity,
            'volume_capacity' => $this->volumeCapacity,
            'pallet_capacity' => $this->palletCapacity,
            'status' => $this->status,
        ];
    }
}
