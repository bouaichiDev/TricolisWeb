<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Tracking;

use App\Modules\Orders\Models\OrderService;
use App\Modules\Tours\Models\TourStop;
use DateTimeInterface;

/**
 * La date à afficher pour une prestation — jamais une date inventée.
 *
 * Priorité, de la plus sûre à la plus vague :
 *
 * 1. l'heure d'arrivée **planifiée** de l'arrêt qui la porte (et son départ) ;
 * 2. le **jour de la tournée**, quand l'arrêt n'a pas encore d'heure ;
 * 3. le jour et le créneau **demandés** sur la prestation.
 *
 * Aucune colonne `calendarDate` : la date se déduit à chaque lecture, elle ne
 * peut donc pas diverger de la planification.
 */
final class ServiceSlot
{
    /**
     * @return array{start: string, end: string|null, allDay: bool, source: string}|null
     */
    public static function of(OrderService $service, ?TourStop $stop): ?array
    {
        if ($stop?->planned_arrival_at !== null) {
            return [
                'start' => self::format($stop->planned_arrival_at),
                'end' => $stop->planned_departure_at === null ? null : self::format($stop->planned_departure_at),
                'allDay' => false,
                'source' => 'planned',
            ];
        }

        if ($stop?->tour?->tour_date !== null) {
            return ['start' => $stop->tour->tour_date->toDateString(), 'end' => null, 'allDay' => true, 'source' => 'planned'];
        }

        if ($service->requested_date === null) {
            return null;
        }

        $date = $service->requested_date->toDateString();
        $from = $service->requested_from?->format('H:i:s');
        $to = $service->requested_to?->format('H:i:s');

        if ($from === null) {
            return ['start' => $date, 'end' => null, 'allDay' => true, 'source' => 'requested'];
        }

        return [
            'start' => $date.'T'.$from,
            'end' => $to === null ? null : $date.'T'.$to,
            'allDay' => false,
            'source' => 'requested',
        ];
    }

    /** Heure murale telle que stockée, sans décalage : c'est l'heure du lieu. */
    public static function format(DateTimeInterface $value): string
    {
        return $value->format('Y-m-d\TH:i:s');
    }
}
