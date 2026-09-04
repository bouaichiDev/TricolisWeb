<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStopService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Recalcule les totaux d'une tournée à partir de son contenu réel.
 *
 * Le §20 interdit d'inventer les formules. Ni le diagramme, ni ses notes, ni
 * les Phases 1 à 3 ne définissent de règle de calcul : cette Action ne dérive
 * donc que ce qui l'est sans ambiguïté, et **laisse le reste intact**.
 *
 * | Champ | Source |
 * |---|---|
 * | `total_weight`, `total_volume`, `total_packages` | services de commande des affectations **actives** |
 * | `total_customers` | clients distincts de ces mêmes commandes |
 * | `distance_meters` | somme des distances des périodes |
 * | `driving_time_minutes`, `working_time_minutes` | **non recalculés** |
 *
 * Les deux derniers exigeraient de distinguer une période de conduite d'une
 * période de service, donc de connaître les valeurs de `periodType` — que le
 * diagramme n'énumère pas. Ils restent saisis par l'appelant.
 *
 * Le recalcul est explicite : appelé par les Actions qui modifient la
 * composition d'une tournée, jamais par un observateur caché.
 */
final readonly class RecalculateTourTotals
{
    public function execute(Tour $tour): Tour
    {
        // Figés pendant une composition, comme {@see TourTotals} : un total qui
        // bougerait trahirait dans les colonnes un plan que personne n'a encore
        // confirmé. Ils sont repris quand la tournée est rendue.
        if ($tour->locked_by !== null) {
            return $tour;
        }

        $totals = $this->serviceTotals($tour);

        $tour->update([
            'total_weight' => $totals->weight ?? 0,
            'total_volume' => $totals->volume ?? 0,
            'total_packages' => $totals->packages ?? 0,
            'total_customers' => $this->distinctCustomerCount($tour),
            'distance_meters' => (int) $tour->periods()->sum('distance_meters'),
        ]);

        return $tour->refresh();
    }

    /**
     * Somme des grandeurs portées par les services planifiés et actifs.
     */
    private function serviceTotals(Tour $tour): object
    {
        return $this->activeServices($tour)
            ->selectRaw('SUM(order_services.weight) as weight')
            ->selectRaw('SUM(order_services.volume) as volume')
            ->selectRaw('SUM(order_services.package_count) as packages')
            ->first() ?? (object) [];
    }

    private function distinctCustomerCount(Tour $tour): int
    {
        return $this->activeServices($tour)
            ->join('orders', 'orders.id', '=', 'order_services.order_id')
            ->distinct()
            ->count('orders.customer_id');
    }

    /**
     * Services actifs de la tournée, joints à leur service de commande.
     *
     * Les affectations désactivées sont exclues : elles décrivent le passé, pas
     * ce que la tournée transporte.
     */
    private function activeServices(Tour $tour): Builder
    {
        return DB::table('tour_stop_services')
            ->join('tour_stops', 'tour_stops.id', '=', 'tour_stop_services.tour_stop_id')
            ->join('order_services', 'order_services.id', '=', 'tour_stop_services.order_service_id')
            ->where('tour_stops.tour_id', $tour->id)
            ->where('tour_stop_services.is_active_assignment', true);
    }

    /**
     * Recalcule la tournée à laquelle appartient un service planifié.
     */
    public function forStopService(TourStopService $service): void
    {
        $tour = $service->tourStop?->tour;

        if ($tour !== null) {
            $this->execute($tour);
        }
    }
}
