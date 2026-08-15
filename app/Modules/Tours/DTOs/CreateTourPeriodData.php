<?php

declare(strict_types=1);

namespace App\Modules\Tours\DTOs;

/**
 * Données de création d'une période.
 *
 * `tourStopId` est facultatif : une période de conduite entre deux arrêts
 * n'appartient à aucun arrêt (`TourStop "0..1" -- "0..*" TourPeriod`).
 */
final readonly class CreateTourPeriodData
{
    public function __construct(
        public string $periodType,
        public int $sequence,
        public string $status,
        public ?string $tourStopId = null,
        public ?string $plannedStartAt = null,
        public ?string $plannedEndAt = null,
        public ?string $actualStartAt = null,
        public ?string $actualEndAt = null,
        public int $breakMinutes = 0,
        public int $serviceMinutes = 0,
        public int $waitingMinutes = 0,
        public int $distanceMeters = 0,
        public ?string $internalRemark = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            periodType: $validated['periodType'],
            sequence: (int) $validated['sequence'],
            status: $validated['status'],
            tourStopId: $validated['tourStopId'] ?? null,
            plannedStartAt: $validated['plannedStartAt'] ?? null,
            plannedEndAt: $validated['plannedEndAt'] ?? null,
            actualStartAt: $validated['actualStartAt'] ?? null,
            actualEndAt: $validated['actualEndAt'] ?? null,
            breakMinutes: (int) ($validated['breakMinutes'] ?? 0),
            serviceMinutes: (int) ($validated['serviceMinutes'] ?? 0),
            waitingMinutes: (int) ($validated['waitingMinutes'] ?? 0),
            distanceMeters: (int) ($validated['distanceMeters'] ?? 0),
            internalRemark: $validated['internalRemark'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $tourId): array
    {
        return [
            'tour_id' => $tourId,
            'tour_stop_id' => $this->tourStopId,
            'period_type' => $this->periodType,
            'sequence' => $this->sequence,
            'planned_start_at' => $this->plannedStartAt,
            'planned_end_at' => $this->plannedEndAt,
            'actual_start_at' => $this->actualStartAt,
            'actual_end_at' => $this->actualEndAt,
            'break_minutes' => $this->breakMinutes,
            'service_minutes' => $this->serviceMinutes,
            'waiting_minutes' => $this->waitingMinutes,
            'distance_meters' => $this->distanceMeters,
            'internal_remark' => $this->internalRemark,
            'status' => $this->status,
        ];
    }
}
