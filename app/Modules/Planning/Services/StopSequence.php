<?php

declare(strict_types=1);

namespace App\Modules\Planning\Services;

use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;

/**
 * Tient l'ordre des arrêts d'une tournée.
 *
 * Séparé du retrait parce que c'est une autre question : ce qui sort de la
 * tournée d'un côté, la suite des rangs de l'autre.
 */
final readonly class StopSequence
{
    /**
     * Retire les arrêts devenus vides et resserre les rangs.
     *
     * Un arrêt sans service actif n'est plus un arrêt : le camion n'y a plus
     * rien à faire. Le §115 demande de compacter les séquences ensuite, sans
     * quoi la tournée garderait des trous que l'ordre d'affichage révèle.
     *
     * **À n'appeler que sur un brouillon.** Ailleurs, supprimer l'arrêt
     * emporterait par cascade l'historique des affectations qu'il porte.
     *
     * @param  list<string>  $stopIds  arrêts touchés par le retrait
     */
    public function pruneAndCompact(Tour $tour, array $stopIds): void
    {
        $emptied = TourStop::whereIn('id', $stopIds)
            ->whereDoesntHave('services', fn ($services) => $services->where('is_active_assignment', true))
            ->get();

        if ($emptied->isEmpty()) {
            return;
        }

        foreach ($emptied as $stop) {
            $stop->delete();
        }

        $this->compact($tour);
    }

    /**
     * Renumérote les arrêts de 1 à n, dans leur ordre courant.
     *
     * Deux temps, comme à la planification : `unique(tour_id, sequence)` refuse
     * qu'un rang soit pris deux fois, même le temps d'une boucle. Les rangs
     * partent donc au-delà du dernier avant de revenir à leur place.
     */
    private function compact(Tour $tour): void
    {
        $remaining = $tour->stops()->orderBy('sequence')->get();

        if ($remaining->isEmpty()) {
            return;
        }

        $offset = (int) $remaining->max('sequence') + 1;

        foreach ($remaining as $index => $stop) {
            $stop->forceFill(['sequence' => $offset + $index])->save();
        }

        foreach ($remaining as $index => $stop) {
            $stop->forceFill(['sequence' => $index + 1])->save();
        }
    }
}
