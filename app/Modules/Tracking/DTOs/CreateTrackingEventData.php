<?php

declare(strict_types=1);

namespace App\Modules\Tracking\DTOs;

/**
 * Données de création d'un événement de suivi.
 *
 * `organizationId` n'est pas dans le payload : il est **forcé** à celui de la
 * commande, ce que le §18 exige (`TrackingEvent.organization_id ==
 * Order.organization_id`). L'accepter en entrée permettrait de le contredire.
 *
 * `createdBy` non plus : c'est l'utilisateur authentifié, ou `null` pour un
 * événement produit par un automate.
 */
final readonly class CreateTrackingEventData
{
    public function __construct(
        public string $orderId,
        public string $eventType,
        public string $status,
        public string $occurredAt,
        public ?string $orderServiceId = null,
        public ?string $tourId = null,
        public ?string $tourStopId = null,
        public ?string $description = null,
        public ?string $latitude = null,
        public ?string $longitude = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            orderId: $validated['orderId'],
            eventType: $validated['eventType'],
            status: $validated['status'],
            occurredAt: $validated['occurredAt'],
            orderServiceId: $validated['orderServiceId'] ?? null,
            tourId: $validated['tourId'] ?? null,
            tourStopId: $validated['tourStopId'] ?? null,
            description: $validated['description'] ?? null,
            latitude: isset($validated['latitude']) ? (string) $validated['latitude'] : null,
            longitude: isset($validated['longitude']) ? (string) $validated['longitude'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $organizationId, ?string $tourId, ?string $createdBy): array
    {
        return [
            'organization_id' => $organizationId,
            'order_id' => $this->orderId,
            'order_service_id' => $this->orderServiceId,
            'tour_id' => $tourId,
            'tour_stop_id' => $this->tourStopId,
            'event_type' => $this->eventType,
            'status' => $this->status,
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'occurred_at' => $this->occurredAt,
            'created_by' => $createdBy,
        ];
    }
}
