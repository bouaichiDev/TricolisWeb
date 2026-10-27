<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Tracking;

use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Modules\Tours\Models\TourStop;

/**
 * L'heure d'arrivée estimée, **seulement quand une donnée la porte**.
 *
 * Tricolis ne calcule aucune ETA en temps réel : aucun moteur ni colonne
 * `estimated_arrival` n'existe. La seule heure d'arrivée enregistrée est celle
 * que la planification pose sur l'arrêt (`planned_arrival_at`). Elle devient une
 * estimation pertinente une fois la tournée partie et tant que l'arrêt n'est
 * pas atteint ; avant, c'est un créneau planifié, après, une arrivée réelle.
 *
 * Hors de ce cas : `available: false`. L'écran dit « ETA indisponible » plutôt
 * que d'en fabriquer une.
 */
final class TrackingEta
{
    /**
     * @return array{available: bool, estimatedAt: string|null, source: string|null}
     */
    public static function forStop(?TourStop $stop): array
    {
        $tour = $stop?->tour;

        if ($stop === null
            || $tour?->status !== TourStatus::IN_PROGRESS
            || $stop->status !== TourStopStatus::PENDING
            || $stop->actual_arrival_at !== null
            || $stop->planned_arrival_at === null) {
            return self::unavailable();
        }

        return [
            'available' => true,
            'estimatedAt' => ServiceSlot::format($stop->planned_arrival_at),
            'source' => 'planning',
        ];
    }

    /**
     * @return array{available: false, estimatedAt: null, source: null}
     */
    public static function unavailable(): array
    {
        return ['available' => false, 'estimatedAt' => null, 'source' => null];
    }
}
