<?php

declare(strict_types=1);

namespace App\Modules\Tours\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'une tournée.
 *
 * `organization_id` n'y figure pas : déplacer une tournée d'une organisation à
 * l'autre emporterait ses arrêts et ses services hors de leur périmètre.
 */
final readonly class UpdateTourData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'tour_number' => 'tourNumber',
        'tour_date' => 'tourDate',
        'agency_id' => 'agencyId',
        'depot_id' => 'depotId',
        'provider_id' => 'providerId',
        'vehicle_id' => 'vehicleId',
        'driver_id' => 'driverId',
        'tour_type' => 'tourType',
        'instructions' => 'instructions',
        'planned_start_at' => 'plannedStartAt',
        'planned_end_at' => 'plannedEndAt',
        'actual_start_at' => 'actualStartAt',
        'actual_end_at' => 'actualEndAt',
        'driving_time_minutes' => 'drivingTimeMinutes',
        'working_time_minutes' => 'workingTimeMinutes',
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
