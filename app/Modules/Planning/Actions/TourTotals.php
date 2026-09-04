<?php

declare(strict_types=1);

namespace App\Modules\Planning\Actions;

use App\Modules\Orders\Models\OrderService;
use App\Modules\Packages\Models\Package;
use App\Modules\Tours\Models\Tour;
use Illuminate\Support\Facades\DB;

/**
 * Recompte ce qu'une tournée transporte.
 *
 * Les colonnes existent depuis la Phase 3 et sont restées à zéro : personne ne
 * les remplissait. Une tournée annoncée sans poids ni colis ne dit rien à qui
 * la prend en charge, et fausse tout contrôle de capacité.
 *
 * **Seules les affectations actives comptent.** Un service replanifié ailleurs
 * laisse derrière lui une affectation historique — la compter ferait porter à
 * cette tournée des colis qu'elle n'emporte plus.
 *
 * Les distances ne sont pas touchées ici : elles viennent du calcul
 * d'itinéraire, qui a son propre déclencheur.
 *
 * **Rien n'est recalculé tant que la tournée est réservée.** C'est ce qui
 * empêche une composition en cours de transparaître dans les colonnes : le
 * contenu y est filtré sur `locked_at`, et les totaux, eux, restent à leur
 * dernière valeur confirmée.
 */
final readonly class TourTotals
{
    public function recalculate(Tour $tour): Tour
    {
        // Figés pendant la composition : les colonnes montrent la tournée telle
        // qu'elle était avant qu'on la prenne, et un total qui bougerait
        // trahirait un plan que personne n'a encore confirmé. Ils sont repris
        // au moment où la tournée est rendue.
        if ($tour->locked_by !== null) {
            return $tour;
        }

        $serviceIds = DB::table('tour_stop_services')
            ->join('tour_stops', 'tour_stops.id', '=', 'tour_stop_services.tour_stop_id')
            ->where('tour_stops.tour_id', $tour->id)
            ->where('tour_stop_services.is_active_assignment', true)
            ->pluck('tour_stop_services.order_service_id');

        if ($serviceIds->isEmpty()) {
            $tour->forceFill([
                'total_weight' => 0,
                'total_volume' => 0,
                'total_packages' => 0,
                'total_customers' => 0,
            ])->save();

            return $tour;
        }

        $orderIds = OrderService::whereIn('id', $serviceIds)->pluck('order_id')->unique();

        // Le nombre de clients, pas de commandes : deux commandes d'un meme
        // client ne font qu'un arret a servir.
        $customers = DB::table('orders')->whereIn('id', $orderIds)
            ->distinct()->count('customer_id');

        $packages = Package::whereIn('order_id', $orderIds)->get();

        $tour->forceFill([
            'total_weight' => (float) $packages->sum('weight'),
            'total_volume' => (float) $packages->sum('volume'),
            'total_packages' => $packages->count(),
            'total_customers' => $customers,
        ])->save();

        return $tour;
    }
}
