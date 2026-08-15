<?php

declare(strict_types=1);

namespace App\Modules\Tours\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'un service planifié.
 *
 * `order_service_id` n'y figure pas : changer le service revient à une autre
 * affectation, qui doit laisser une trace. On désactive puis on recrée.
 */
final readonly class UpdateTourStopServiceData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'sequence_within_stop' => 'sequenceWithinStop',
        'is_active_assignment' => 'isActiveAssignment',
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
