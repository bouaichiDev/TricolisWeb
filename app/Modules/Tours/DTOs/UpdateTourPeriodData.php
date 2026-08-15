<?php

declare(strict_types=1);

namespace App\Modules\Tours\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'une période.
 */
final readonly class UpdateTourPeriodData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'tour_stop_id' => 'tourStopId',
        'period_type' => 'periodType',
        'sequence' => 'sequence',
        'planned_start_at' => 'plannedStartAt',
        'planned_end_at' => 'plannedEndAt',
        'actual_start_at' => 'actualStartAt',
        'actual_end_at' => 'actualEndAt',
        'break_minutes' => 'breakMinutes',
        'service_minutes' => 'serviceMinutes',
        'waiting_minutes' => 'waitingMinutes',
        'distance_meters' => 'distanceMeters',
        'internal_remark' => 'internalRemark',
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
