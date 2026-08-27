<?php

declare(strict_types=1);

namespace App\Modules\Planning\Actions;

use App\Modules\Planning\Services\GeocodingService;
use App\Modules\Planning\Services\RoutingService;
use App\Modules\Tours\Actions\RecalculateTourTotals;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Recalcule l'itinéraire d'une tournée à partir de ses arrêts.
 *
 * **Aucune table n'est créée.** Le modèle prévoyait déjà où ranger un segment :
 * `tour_periods` porte `distance_meters` et se rattache à l'arrêt qu'elle
 * dessert. Un trajet entre deux arrêts devient donc une période de type
 * `driving`, attachée à l'arrêt d'arrivée — et `RecalculateTourTotals` en fait
 * déjà la somme dans `tours.distance_meters`, sans qu'une ligne change.
 *
 * `driving_time_minutes` est posé ici, et nulle part ailleurs : le recalcul des
 * totaux le laisse délibérément intact, faute de savoir distinguer une période
 * de conduite d'une période de service. Cette Action, elle, le sait : ce sont
 * ses propres périodes.
 *
 * **Tout ou rien.** Si un seul segment manque — service injoignable, arrêt non
 * géocodé — l'itinéraire est effacé plutôt que laissé partiel : une tournée
 * annoncée plus courte qu'elle ne l'est fait rater des rendez-vous, et
 * l'écran sait dire « non calculé ».
 *
 * **Les adresses manquantes sont situées en chemin.** Le §83 demande de géocoder
 * « uniquement les adresses nécessaires à l'opération » : c'est ici qu'on sait
 * lesquelles, et une commande planifiée sur une adresse jamais située doit
 * obtenir son point maintenant plutôt que jamais. Le coût ne se voit pas :
 * l'Action tourne déjà en file.
 *
 * `workingTimeMinutes` n'est pas touché : le §94 interdit d'y recopier le temps
 * de conduite, et le service ne rend ni les temps de service ni les pauses.
 */
final readonly class RecalculateTourRoute
{
    /** Type de période porté par un trajet, celui qu'emploient déjà les tests. */
    public const string PERIOD_TYPE = 'driving';

    public function __construct(
        private GeocodingService $geocoding,
        private RoutingService $routing,
        private RecalculateTourTotals $totals,
    ) {}

    /**
     * @return int nombre de segments calculés ; zéro quand l'itinéraire est inconnu
     */
    public function execute(Tour $tour): int
    {
        $stops = $tour->stops()->orderBy('sequence')->with('address')->get();

        $points = [];

        foreach ($stops as $stop) {
            $address = $stop->address;

            if ($address === null) {
                return $this->forget($tour);
            }

            // Le geocodage a la demande, exige par le §83 : c'est ici, et
            // seulement ici, qu'on sait quelles adresses l'operation reclame.
            // Une commande planifiee sur une adresse jamais situee doit obtenir
            // son point maintenant, sinon la tournee n'aurait jamais de trace.
            if ($address->latitude === null || $address->longitude === null) {
                $this->geocoding->locate($address, $tour->organization_id);
                $address->refresh();
            }

            if ($address->latitude === null || $address->longitude === null) {
                // Un arret sans point rend la suite du trajet indeterminee :
                // relier ses voisins par-dessus inventerait une route qui ne
                // passe pas par la ou le camion s'arrete.
                return $this->forget($tour);
            }

            $points[] = [(float) $address->latitude, (float) $address->longitude];
        }

        $legs = $this->routing->legs($points, $tour->organization_id);

        if ($legs === []) {
            return $this->forget($tour);
        }

        DB::transaction(function () use ($tour, $stops, $legs): void {
            $this->clear($tour);

            // Le premier arret n'a pas de trajet entrant : le segment `n` mene
            // a l'arret `n + 1`.
            $offset = (int) $tour->periods()->max('sequence');

            foreach ($legs as $index => $leg) {
                TourPeriod::create([
                    'tour_id' => $tour->id,
                    'tour_stop_id' => $stops[$index + 1]->id,
                    'period_type' => self::PERIOD_TYPE,
                    'sequence' => $offset + $index + 1,
                    'distance_meters' => $leg->distanceMeters,
                    'status' => 'planned',
                ]);
            }

            $tour->forceFill([
                'driving_time_minutes' => array_sum(
                    array_map(static fn ($leg): int => $leg->travelMinutes(), $legs),
                ),
            ])->save();
        });

        $this->totals->execute($tour);

        return count($legs);
    }

    /**
     * Efface l'itinéraire connu.
     *
     * Garder l'ancien serait pire que rien : il décrirait un ordre d'arrêts qui
     * n'existe plus, et personne ne saurait qu'il est périmé.
     */
    private function forget(Tour $tour): int
    {
        DB::transaction(function () use ($tour): void {
            $this->clear($tour);
            $tour->forceFill(['driving_time_minutes' => 0])->save();
        });

        $this->totals->execute($tour);

        return 0;
    }

    /**
     * Ne retire que les périodes de trajet.
     *
     * Les périodes de service, de pause ou d'attente sont saisies par ailleurs :
     * un recalcul d'itinéraire n'a pas à les emporter.
     */
    private function clear(Tour $tour): void
    {
        $tour->periods()->where('period_type', self::PERIOD_TYPE)->delete();
    }
}
