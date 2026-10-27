<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Tracking;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;

/**
 * Rassemble l'état de suivi d'une commande à partir des données existantes :
 * prestations, arrêts planifiés, parcours, preuves, position et ETA.
 *
 * Point d'entrée unique du suivi authentifié **et** du lien public : les deux
 * lisent le même état, seuls leurs présentateurs diffèrent.
 */
final readonly class OrderTrackingBuilder
{
    /** Statuts où la commande n'évolue plus : rien à rafraîchir. */
    private const array SETTLED = [
        OrderStatus::COMPLETED,
        OrderStatus::CANCELLED,
        OrderStatus::PARTIALLY_INVOICED,
        OrderStatus::INVOICED,
    ];

    public function __construct(
        private ServicePlanning $planning,
        private TimelineSources $sources,
        private TrackingPosition $positions,
    ) {}

    public function build(Order $order): OrderTrackingSnapshot
    {
        $order->loadMissing(['orderServices' => static fn ($query) => $query->orderBy('sequence'), 'orderServices.service:id,code,name', 'orderServices.address']);

        $services = $order->orderServices;
        $stops = $this->planning->stopsFor($services, $order->organization_id);
        $definitions = $this->sources->journeyDefinitions($order);
        $events = $this->sources->journeyEvents($order, $definitions);
        $proofs = $this->sources->proofs($order);

        $tracked = $services->map(function (OrderService $service) use ($stops, $definitions, $events, $proofs): TrackedService {
            $stop = $stops[$service->id] ?? null;

            return new TrackedService(
                service: $service,
                stop: $stop,
                slot: ServiceSlot::of($service, $stop),
                eta: TrackingEta::forStop($stop),
                position: $stop === null ? null : $this->positions->current($stop),
                journey: JourneyProgress::of($service, $definitions, $events),
                proof: $proofs->last(static fn ($proof): bool => $proof->order_service_id === $service->id),
            );
        })->values()->all();

        return new OrderTrackingSnapshot(
            order: $order,
            // Le suivi existe dès qu'une donnée d'exécution existe : un arrêt
            // planifié, une étape du parcours franchie, une preuve. Avant,
            // l'écran dit « suivi indisponible » plutôt qu'une carte vide.
            available: $stops !== [] || $events->isNotEmpty() || $proofs->isNotEmpty(),
            active: ! in_array($order->status, self::SETTLED, true),
            services: $tracked,
            statusChanges: $this->sources->statusChanges($order),
            journeyEvents: $events,
            proofs: $proofs,
        );
    }
}
