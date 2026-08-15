<?php

declare(strict_types=1);

namespace App\Modules\Tours\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'une affectation.
 *
 * `tour_period_id` n'y figure pas : la période est le parent de la route, et
 * déplacer une affectation reviendrait à la recréer ailleurs.
 */
final readonly class UpdateTourPeriodAssignmentData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'tour_stop_service_id' => 'tourStopServiceId',
        'package_id' => 'packageId',
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
