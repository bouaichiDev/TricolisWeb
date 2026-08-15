<?php

declare(strict_types=1);

namespace App\Modules\Fleet\DTOs;

/**
 * Données de création d'un type de véhicule.
 */
final readonly class CreateVehicleTypeData
{
    public function __construct(
        public string $code,
        public string $name,
        public string $status,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            code: $validated['code'],
            name: $validated['name'],
            status: $validated['status'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $organizationId): array
    {
        return [
            'organization_id' => $organizationId,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
        ];
    }
}
