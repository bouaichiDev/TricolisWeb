<?php

declare(strict_types=1);

namespace App\Modules\Tours\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'un arrêt.
 *
 * `tour_id` n'y figure pas : déplacer un arrêt d'une tournée à l'autre romprait
 * la composition et l'unicité de séquence. Il faut le supprimer et le recréer.
 */
final readonly class UpdateTourStopData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'address_id' => 'addressId',
        'sequence' => 'sequence',
        'grouping_key' => 'groupingKey',
        'generation_mode' => 'generationMode',
        'planned_arrival_at' => 'plannedArrivalAt',
        'planned_departure_at' => 'plannedDepartureAt',
        'actual_arrival_at' => 'actualArrivalAt',
        'actual_departure_at' => 'actualDepartureAt',
        'waiting_minutes' => 'waitingMinutes',
        'service_minutes' => 'serviceMinutes',
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
