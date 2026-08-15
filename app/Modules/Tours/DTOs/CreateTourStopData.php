<?php

declare(strict_types=1);

namespace App\Modules\Tours\DTOs;

/**
 * Données de création d'un arrêt, services compris.
 *
 * La cardinalité `TourStop "1" *-- "1..*" TourStopService` interdit un arrêt
 * sans service : les services font donc partie de la création, et sont écrits
 * dans la même transaction. Un arrêt vide n'existe jamais, même transitoirement.
 *
 * @param  list<CreateTourStopServiceData>  $services
 */
final readonly class CreateTourStopData
{
    /**
     * @param  list<CreateTourStopServiceData>  $services
     */
    public function __construct(
        public string $addressId,
        public int $sequence,
        public string $status,
        public array $services,
        public ?string $groupingKey = null,
        public ?string $generationMode = null,
        public ?string $plannedArrivalAt = null,
        public ?string $plannedDepartureAt = null,
        public ?string $actualArrivalAt = null,
        public ?string $actualDepartureAt = null,
        public int $waitingMinutes = 0,
        public int $serviceMinutes = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            addressId: $validated['addressId'],
            sequence: (int) $validated['sequence'],
            status: $validated['status'],
            services: array_map(
                static fn (array $service): CreateTourStopServiceData => CreateTourStopServiceData::fromValidated($service),
                $validated['services'],
            ),
            groupingKey: $validated['groupingKey'] ?? null,
            generationMode: $validated['generationMode'] ?? null,
            plannedArrivalAt: $validated['plannedArrivalAt'] ?? null,
            plannedDepartureAt: $validated['plannedDepartureAt'] ?? null,
            actualArrivalAt: $validated['actualArrivalAt'] ?? null,
            actualDepartureAt: $validated['actualDepartureAt'] ?? null,
            waitingMinutes: (int) ($validated['waitingMinutes'] ?? 0),
            serviceMinutes: (int) ($validated['serviceMinutes'] ?? 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $tourId): array
    {
        return [
            'tour_id' => $tourId,
            'address_id' => $this->addressId,
            'sequence' => $this->sequence,
            'grouping_key' => $this->groupingKey,
            'generation_mode' => $this->generationMode,
            'planned_arrival_at' => $this->plannedArrivalAt,
            'planned_departure_at' => $this->plannedDepartureAt,
            'actual_arrival_at' => $this->actualArrivalAt,
            'actual_departure_at' => $this->actualDepartureAt,
            'waiting_minutes' => $this->waitingMinutes,
            'service_minutes' => $this->serviceMinutes,
            'status' => $this->status,
        ];
    }
}
