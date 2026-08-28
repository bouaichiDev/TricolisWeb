<?php

declare(strict_types=1);

namespace App\Modules\Planning\Services;

use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;
use Illuminate\Support\Collection;

/**
 * Ce qu'une tournée montre au reste de l'application pendant qu'on la compose.
 *
 * **Une composition en cours ne se voit pas ailleurs.** Décision du 28 août
 * 2026 : tant que le planificateur n'a pas confirmé, la vue en colonnes doit
 * afficher la tournée telle qu'elle était avant qu'il ne commence. Sinon un
 * collègue lit un plan à moitié fait et le prend pour acquis.
 *
 * **Le pivot est `confirmed_at`.** Nulle, l'affectation appartient à la
 * composition en cours ; renseignée, elle est acquise et visible partout.
 *
 * Une première version comparait la création de l'affectation à la prise de la
 * tournée. `tour_stop_services` ne porte aucun horodatage — `$timestamps` y est
 * faux — et la comparaison portait donc sur `null` : tout restait visible. Une
 * colonne qui dit ce qu'elle veut dire vaut mieux qu'une déduction suspendue à
 * un détail de schéma.
 */
final readonly class ConfirmedContent
{
    /**
     * Les services de cet arrêt que le reste de l'application doit voir.
     *
     * @return Collection<int, TourStopService>
     */
    public function servicesOf(Tour $tour, TourStop $stop): Collection
    {
        /** @var Collection<int, TourStopService> $services */
        $services = $stop->relationLoaded('services') ? $stop->services : collect();

        return $services
            ->where('is_active_assignment', true)
            ->filter(fn (TourStopService $service): bool => $service->confirmed_at !== null)
            ->values();
    }

    /**
     * Combien de changements attendent d'être confirmés.
     *
     * Sert à le dire plutôt qu'à le taire : une tournée dont le contenu semble
     * figé alors qu'on la compose ailleurs inquiéterait sans raison.
     */
    public function pendingCount(Tour $tour): int
    {
        if (! $tour->relationLoaded('stops')) {
            return 0;
        }

        return $tour->stops
            ->flatMap(fn (TourStop $stop) => $stop->relationLoaded('services') ? $stop->services : collect())
            ->where('is_active_assignment', true)
            ->filter(fn (TourStopService $service): bool => $service->confirmed_at === null)
            ->count();
    }
}
