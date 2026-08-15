<?php

declare(strict_types=1);

namespace App\Modules\Tours\DTOs;

/**
 * Affectation d'un service — et éventuellement d'un colis — à une période.
 *
 * Le diagramme ne pose que trois clés étrangères : ni séquence, ni statut, ni
 * quantité, ni durée.
 */
final readonly class CreateTourPeriodAssignmentData
{
    public function __construct(
        public string $tourStopServiceId,
        public ?string $packageId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            tourStopServiceId: $validated['tourStopServiceId'],
            packageId: $validated['packageId'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $tourPeriodId): array
    {
        return [
            'tour_period_id' => $tourPeriodId,
            'tour_stop_service_id' => $this->tourStopServiceId,
            'package_id' => $this->packageId,
        ];
    }
}
