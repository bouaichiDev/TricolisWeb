<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Tracking;

use App\Modules\Orders\Models\OrderService;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;

/**
 * L'arrêt de tournée qui exécute chaque prestation, et rien d'autre.
 *
 * **C'est ici que se joue l'isolation d'une tournée partagée.** Une tournée
 * dessert IKEA, QOQA et Schmidt ; le portail ne remonte jamais de la tournée
 * vers ses arrêts. Il part des prestations de *la* commande, suit leur
 * affectation active, et s'arrête à l'arrêt qui les porte. Les autres arrêts,
 * leurs adresses et leurs commandes ne sont tout simplement jamais chargés.
 *
 * De la tournée, seules les colonnes utiles au suivi sont lues — ni chauffeur,
 * ni véhicule, ni fournisseur, ni totaux.
 */
final readonly class ServicePlanning
{
    private const string TOUR_COLUMNS = 'id,organization_id,tour_date,status,telematics_reference,actual_start_at';

    /**
     * @param  iterable<int, OrderService>  $services
     * @return array<string, TourStop> arrêt indexé par identifiant de prestation
     */
    public function stopsFor(iterable $services, string $organizationId): array
    {
        $ids = collect($services)->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $assignments = TourStopService::query()
            ->with('tourStop.tour:'.self::TOUR_COLUMNS)
            ->whereIn('order_service_id', $ids)
            ->where('is_active_assignment', true)
            ->get();

        $stops = [];

        foreach ($assignments as $assignment) {
            $stop = $assignment->tourStop;

            // Défense en profondeur : une tournée d'une autre organisation ne
            // saurait porter une prestation de cette commande.
            if ($stop === null || $stop->tour?->organization_id !== $organizationId) {
                continue;
            }

            $stops[$assignment->order_service_id] = $stop;
        }

        return $stops;
    }
}
