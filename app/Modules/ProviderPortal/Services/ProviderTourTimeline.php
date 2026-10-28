<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Services;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Models\TrackingEventDefinition;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * La chronologie d'une tournée, telle que le portail fournisseur la montre.
 *
 * Elle n'invente rien : horodatages réels de la tournée et de ses arrêts,
 * passages d'arrêt audités, et événements de suivi rattachés à **cette**
 * tournée dans son organisation. La note libre d'un événement (`description`)
 * n'est jamais rendue — elle peut porter une remarque du client ou de
 * l'interne ; seul le libellé du référentiel l'est. Les positions ne figurent
 * que si elles ont été réellement enregistrées.
 *
 * @phpstan-type Entry array{type: string, label: string, occurredAt: string, stopSequence: int|null, serviceNumber: string|null, latitude: float|null, longitude: float|null}
 */
final class ProviderTourTimeline
{
    /**
     * @return list<Entry>
     */
    public function for(Tour $tour): array
    {
        $tour->loadMissing('stops');
        $sequences = $tour->stops->pluck('sequence', 'id');
        $entries = [];

        $this->add($entries, 'tour_started', 'Tournée démarrée', $tour->actual_start_at);
        $this->add($entries, 'tour_completed', 'Tournée terminée', $tour->actual_end_at);

        foreach ($tour->stops as $stop) {
            $this->add($entries, 'stop_arrived', 'Arrivée à l’arrêt', $stop->actual_arrival_at, $stop->sequence);
            $this->add($entries, 'stop_departed', 'Arrêt terminé', $stop->actual_departure_at, $stop->sequence);
        }

        $skips = AuditLog::where('organization_id', $tour->organization_id)
            ->where('action', 'tour_stop.skipped')
            ->whereIn('entity_id', $sequences->keys())
            ->get(['entity_id', 'created_at']);

        foreach ($skips as $log) {
            $this->add($entries, 'stop_skipped', 'Arrêt passé', $log->created_at, $sequences[$log->entity_id] ?? null);
        }

        $labels = TrackingEventDefinition::where('organization_id', $tour->organization_id)->pluck('title', 'code');

        $events = TrackingEvent::with('orderService:id,service_number')
            ->where('organization_id', $tour->organization_id)
            ->where('tour_id', $tour->id)
            ->get();

        // Boucle, pas `each(fn …)` : une fonction fléchée capture `$entries` par valeur.
        foreach ($events as $event) {
            $this->add(
                $entries,
                'event',
                (string) ($labels[$event->event_type] ?? Str::ucfirst(str_replace('_', ' ', (string) $event->event_type))),
                $event->occurred_at,
                $event->tour_stop_id === null ? null : ($sequences[$event->tour_stop_id] ?? null),
                $event->orderService?->service_number,
                $event->latitude === null ? null : (float) $event->latitude,
                $event->longitude === null ? null : (float) $event->longitude,
            );
        }

        usort($entries, static fn (array $a, array $b): int => strcmp($a['occurredAt'], $b['occurredAt']));

        return $entries;
    }

    /**
     * @param  list<Entry>  $entries
     */
    private function add(array &$entries, string $type, string $label, ?CarbonInterface $at, ?int $stop = null, ?string $service = null, ?float $lat = null, ?float $lng = null): void
    {
        if ($at === null) {
            return;
        }

        $entries[] = [
            'type' => $type,
            'label' => $label,
            'occurredAt' => $at->toIso8601String(),
            'stopSequence' => $stop,
            'serviceNumber' => $service,
            'latitude' => $lat,
            'longitude' => $lng,
        ];
    }
}
