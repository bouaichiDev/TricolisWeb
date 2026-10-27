<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Tracking;

use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Models\OrderService;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\Tours\Models\TourStop;

/**
 * Une prestation telle que le suivi la voit : son arrêt (s'il est planifié),
 * sa date, son ETA, la position qui la concerne, sa progression dans le
 * parcours et sa preuve de livraison.
 *
 * Objet de lecture, construit par `OrderTrackingBuilder` ; les présentateurs
 * choisissent ensuite ce qui sort, pour le client ou pour le lien public.
 */
final readonly class TrackedService
{
    /**
     * @param  array{start: string, end: string|null, allDay: bool, source: string}|null  $slot
     * @param  array{available: bool, estimatedAt: string|null, source: string|null}  $eta
     * @param  array{latitude: float, longitude: float, recordedAt: string|null}|null  $position
     * @param  array{reached: int, total: int, percent: int}|null  $journey
     */
    public function __construct(
        public OrderService $service,
        public ?TourStop $stop,
        public ?array $slot,
        public array $eta,
        public ?array $position,
        public ?array $journey,
        public ?ProofOfDelivery $proof,
    ) {}

    /** Effectuée — ou déjà facturée, ce qui suppose qu'elle a été effectuée. */
    public function completed(): bool
    {
        return in_array($this->service->status, [OrderServiceStatus::COMPLETED, OrderServiceStatus::INVOICED], true);
    }

    /** Une prestation annulée ne compte plus dans la progression. */
    public function cancelled(): bool
    {
        return $this->service->status === OrderServiceStatus::CANCELLED;
    }

    public function name(): string
    {
        return $this->service->service?->name ?? 'Prestation '.$this->service->service_number;
    }
}
