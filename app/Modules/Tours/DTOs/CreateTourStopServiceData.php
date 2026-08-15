<?php

declare(strict_types=1);

namespace App\Modules\Tours\DTOs;

/**
 * Affectation d'un service de commande à un arrêt.
 *
 * `isActiveAssignment` vaut `true` par défaut : une affectation créée est
 * l'affectation courante. L'historique se construit en désactivant les
 * précédentes, jamais en les supprimant.
 */
final readonly class CreateTourStopServiceData
{
    public function __construct(
        public string $orderServiceId,
        public int $sequenceWithinStop,
        public string $status,
        public bool $isActiveAssignment = true,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            orderServiceId: $validated['orderServiceId'],
            sequenceWithinStop: (int) $validated['sequenceWithinStop'],
            status: $validated['status'],
            isActiveAssignment: (bool) ($validated['isActiveAssignment'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $tourStopId): array
    {
        return [
            'tour_stop_id' => $tourStopId,
            'order_service_id' => $this->orderServiceId,
            'sequence_within_stop' => $this->sequenceWithinStop,
            'is_active_assignment' => $this->isActiveAssignment,
            'status' => $this->status,
        ];
    }
}
