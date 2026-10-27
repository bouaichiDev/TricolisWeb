<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Tracking;

use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Services\VehiclePositionProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * La position du véhicule, **quand** elle concerne la prestation du client.
 *
 * Elle n'est exposée que si la tournée roule et que l'arrêt de la prestation
 * n'est pas encore servi : avant, le véhicule dessert d'autres clients ; après,
 * il ne concerne plus celui-ci. Jamais l'historique — un seul point, le
 * dernier. Jamais le chauffeur ni le véhicule — des coordonnées et une heure.
 *
 * Deux sources existantes, dans l'ordre :
 *
 * 1. la télématique de l'organisation (`VehiclePositionProvider`), par la
 *    référence de la tournée — le jeton du fournisseur reste au serveur ;
 * 2. le dernier événement de suivi géolocalisé de la tournée depuis son départ.
 *
 * Le point est mémorisé quelques secondes par tournée : dix clients qui
 * rafraîchissent ne font pas dix appels au fournisseur.
 */
final readonly class TrackingPosition
{
    /** Arrêts dont le passage reste à venir ou en cours. */
    private const array OPEN_STOP_STATUSES = [TourStopStatus::PENDING, TourStopStatus::ARRIVED, TourStopStatus::IN_PROGRESS];

    public function __construct(private VehiclePositionProvider $provider) {}

    /**
     * @return array{latitude: float, longitude: float, recordedAt: string|null}|null
     */
    public function current(TourStop $stop): ?array
    {
        $tour = $stop->tour;

        if ($tour === null || ! $this->exposable($tour, $stop)) {
            return null;
        }

        $cached = Cache::remember(
            'customer-tracking:position:'.$tour->id,
            Config::integer('customer_portal.tracking.position_cache_seconds', 30),
            fn (): array => ['point' => $this->telematics($tour) ?? $this->lastEvent($tour)],
        );

        return $cached['point'];
    }

    public function exposable(Tour $tour, TourStop $stop): bool
    {
        return $tour->status === TourStatus::IN_PROGRESS
            && in_array($stop->status, self::OPEN_STOP_STATUSES, true);
    }

    /**
     * @return array{latitude: float, longitude: float, recordedAt: string|null}|null
     */
    private function telematics(Tour $tour): ?array
    {
        if (! is_string($tour->telematics_reference) || $tour->telematics_reference === '') {
            return null;
        }

        $configuration = OrganizationApiConfiguration::query()
            ->where('organization_id', $tour->organization_id)
            ->where('code', VehiclePositionProvider::CONFIGURATION_CODE)
            ->where('is_active', true)
            ->first();

        if ($configuration === null) {
            return null;
        }

        $last = collect($this->provider->forReference($configuration, $tour->telematics_reference))->last();

        return $last === null ? null : [
            'latitude' => $last['latitude'],
            'longitude' => $last['longitude'],
            'recordedAt' => $last['occurredAt'],
        ];
    }

    /**
     * @return array{latitude: float, longitude: float, recordedAt: string|null}|null
     */
    private function lastEvent(Tour $tour): ?array
    {
        $event = TrackingEvent::query()
            ->where('organization_id', $tour->organization_id)
            ->where('tour_id', $tour->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($tour->actual_start_at !== null, static fn ($query) => $query->where('occurred_at', '>=', $tour->actual_start_at))
            ->latest('occurred_at')
            ->first(['latitude', 'longitude', 'occurred_at']);

        return $event === null ? null : [
            'latitude' => (float) $event->latitude,
            'longitude' => (float) $event->longitude,
            'recordedAt' => $event->occurred_at?->toIso8601String(),
        ];
    }
}
