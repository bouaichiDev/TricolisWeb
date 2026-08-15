<?php

declare(strict_types=1);

namespace App\Modules\Tours\DTOs;

/**
 * Données de création d'une tournée.
 *
 * `organizationId` n'est pas dans le payload : il vient du contexte actif.
 *
 * Les sept totaux ne sont pas acceptés en entrée hormis les deux que le projet
 * ne sait pas recalculer (`drivingTimeMinutes`, `workingTimeMinutes`) : les
 * autres sont dérivés du contenu de la tournée par `RecalculateTourTotals`.
 */
final readonly class CreateTourData
{
    public function __construct(
        public string $tourNumber,
        public string $tourDate,
        public string $agencyId,
        public string $status,
        public ?string $depotId = null,
        public ?string $providerId = null,
        public ?string $vehicleId = null,
        public ?string $driverId = null,
        public ?string $tourType = null,
        public ?string $instructions = null,
        public ?string $plannedStartAt = null,
        public ?string $plannedEndAt = null,
        public ?string $actualStartAt = null,
        public ?string $actualEndAt = null,
        public int $drivingTimeMinutes = 0,
        public int $workingTimeMinutes = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            tourNumber: $validated['tourNumber'],
            tourDate: $validated['tourDate'],
            agencyId: $validated['agencyId'],
            status: $validated['status'],
            depotId: $validated['depotId'] ?? null,
            providerId: $validated['providerId'] ?? null,
            vehicleId: $validated['vehicleId'] ?? null,
            driverId: $validated['driverId'] ?? null,
            tourType: $validated['tourType'] ?? null,
            instructions: $validated['instructions'] ?? null,
            plannedStartAt: $validated['plannedStartAt'] ?? null,
            plannedEndAt: $validated['plannedEndAt'] ?? null,
            actualStartAt: $validated['actualStartAt'] ?? null,
            actualEndAt: $validated['actualEndAt'] ?? null,
            drivingTimeMinutes: (int) ($validated['drivingTimeMinutes'] ?? 0),
            workingTimeMinutes: (int) ($validated['workingTimeMinutes'] ?? 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $organizationId): array
    {
        return [
            'organization_id' => $organizationId,
            'tour_number' => $this->tourNumber,
            'tour_date' => $this->tourDate,
            'agency_id' => $this->agencyId,
            'depot_id' => $this->depotId,
            'provider_id' => $this->providerId,
            'vehicle_id' => $this->vehicleId,
            'driver_id' => $this->driverId,
            'tour_type' => $this->tourType,
            'instructions' => $this->instructions,
            'planned_start_at' => $this->plannedStartAt,
            'planned_end_at' => $this->plannedEndAt,
            'actual_start_at' => $this->actualStartAt,
            'actual_end_at' => $this->actualEndAt,
            'driving_time_minutes' => $this->drivingTimeMinutes,
            'working_time_minutes' => $this->workingTimeMinutes,
            'status' => $this->status,
        ];
    }
}
