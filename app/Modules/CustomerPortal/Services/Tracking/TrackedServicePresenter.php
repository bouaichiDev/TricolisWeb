<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Tracking;

use App\Modules\CustomerPortal\Services\CustomerStatusLabels;
use App\Shared\Database\MorphMap;

/**
 * Une prestation suivie, pour le membre authentifié du portail.
 *
 * L'adresse est celle de **la prestation** — que le client a lui-même saisie —
 * jamais celle d'un autre arrêt. De l'arrêt ne sortent que ses heures et son
 * statut : ni séquence dans la tournée, ni autres services, ni chauffeur.
 */
final readonly class TrackedServicePresenter
{
    public function __construct(private CustomerStatusLabels $labels) {}

    /**
     * @return array<string, mixed>
     */
    public function present(TrackedService $tracked): array
    {
        $service = $tracked->service;
        $address = $service->address;
        $stop = $tracked->stop;

        return [
            'id' => $service->id,
            'serviceNumber' => $service->service_number,
            'sequence' => $service->sequence,
            'service' => $service->service === null ? null : ['code' => $service->service->code, 'name' => $service->service->name],
            'status' => $this->labels->present(MorphMap::ORDER_SERVICE, $service->status),
            'completed' => $tracked->completed(),
            'address' => $address === null ? null : [
                'name' => $address->name,
                'addressLine1' => $address->address_line_1,
                'postalCode' => $address->postal_code,
                'city' => $address->city,
                'country' => $address->country,
                'latitude' => $address->latitude === null ? null : (float) $address->latitude,
                'longitude' => $address->longitude === null ? null : (float) $address->longitude,
            ],
            'requested' => [
                'date' => $service->requested_date?->toDateString(),
                'from' => $service->requested_from?->format('H:i'),
                'to' => $service->requested_to?->format('H:i'),
            ],
            'slot' => $tracked->slot,
            'stop' => $stop === null ? null : [
                'status' => $this->labels->present(MorphMap::TOUR_STOP, $stop->status),
                'plannedArrivalAt' => $stop->planned_arrival_at === null ? null : ServiceSlot::format($stop->planned_arrival_at),
                'plannedDepartureAt' => $stop->planned_departure_at === null ? null : ServiceSlot::format($stop->planned_departure_at),
                'actualArrivalAt' => $stop->actual_arrival_at?->toIso8601String(),
                'actualDepartureAt' => $stop->actual_departure_at?->toIso8601String(),
            ],
            'eta' => $tracked->eta,
            'progress' => $tracked->journey,
            'currentPosition' => $tracked->position,
            'proofOfDelivery' => $tracked->proof === null ? null : [
                'deliveredAt' => $tracked->proof->delivered_at?->toIso8601String(),
                'recipientName' => $tracked->proof->recipient_name,
            ],
        ];
    }
}
