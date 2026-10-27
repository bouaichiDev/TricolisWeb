<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Tracking;

use App\Modules\CustomerPortal\Services\CustomerStatusLabels;
use App\Shared\Database\MorphMap;
use Illuminate\Support\Facades\Config;

/**
 * Le suivi d'une commande, pour le membre authentifié du portail (§12).
 *
 * Construit champ par champ depuis l'état de suivi : aucun modèle n'est
 * sérialisé tel quel, donc aucune colonne interne ne peut s'y glisser.
 */
final readonly class CustomerTrackingPresenter
{
    public function __construct(
        private CustomerStatusLabels $labels,
        private TrackedServicePresenter $services,
        private CustomerTrackingTimeline $timeline,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(OrderTrackingSnapshot $snapshot): array
    {
        $order = $snapshot->order;

        return [
            'order' => [
                'id' => $order->id,
                'reference' => $order->order_number,
                'customerReference' => $order->customer_reference,
                'orderDate' => $order->order_date?->toIso8601String(),
                'status' => $this->labels->present(MorphMap::ORDER, $order->status),
            ],
            'available' => $snapshot->available,
            'isActive' => $snapshot->active,
            'refreshSeconds' => $snapshot->active ? Config::integer('customer_portal.tracking.refresh_seconds', 45) : null,
            'progress' => $snapshot->progress(),
            'services' => array_map($this->services->present(...), $snapshot->services),
            'timeline' => $this->timeline->for($snapshot),
            'map' => $this->map($snapshot),
            'eta' => $snapshot->eta(),
        ];
    }

    /**
     * Le suivi d'une seule prestation, avec les entrées de timeline qui la concernent.
     *
     * @return array<string, mixed>
     */
    public function presentService(OrderTrackingSnapshot $snapshot, TrackedService $tracked): array
    {
        $timeline = array_values(array_filter(
            $this->timeline->for($snapshot),
            static fn (array $item): bool => $item['orderServiceId'] === $tracked->service->id,
        ));

        return ['service' => $this->services->present($tracked), 'timeline' => $timeline];
    }

    /**
     * Les points que la carte peut montrer : les adresses géocodées des
     * prestations, et la position actuelle quand elle est exposable.
     *
     * `available` suit le §12 : il dit si une **position** est connue. Les
     * adresses géocodées suffisent à dessiner une carte sans elle.
     *
     * @return array{available: bool, currentPosition: array<string, mixed>|null, points: list<array<string, mixed>>}
     */
    private function map(OrderTrackingSnapshot $snapshot): array
    {
        $points = [];

        foreach ($snapshot->services as $tracked) {
            $address = $tracked->service->address;

            if ($address?->latitude !== null && $address->longitude !== null) {
                $points[] = [
                    'orderServiceId' => $tracked->service->id,
                    'label' => $tracked->name(),
                    'latitude' => (float) $address->latitude,
                    'longitude' => (float) $address->longitude,
                    'completed' => $tracked->completed(),
                ];
            }
        }

        $position = $snapshot->position();

        return [
            'available' => $position !== null,
            'currentPosition' => $position,
            'points' => $points,
        ];
    }
}
