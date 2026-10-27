<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use Illuminate\Support\Facades\DB;

/**
 * Ce qui, en aval, s'est déjà appuyé sur le contenu d'une commande.
 *
 * Deux questions, posées pour plusieurs commandes à la fois (une liste en
 * affiche vingt-cinq) :
 *
 * - **planifiée** : un de ses services est sur une tournée, affectation
 *   active. Le client ne peut plus ni modifier ni annuler seul : la tournée
 *   construite dessus ne serait pas prévenue ;
 * - **engagée** : un élément de la commande est référencé ailleurs — tournée
 *   (même passée), facture, règlement fournisseur, colis affecté à une
 *   période, stock réservé. Réécrire son contenu casserait ces liens, que la
 *   base protège d'ailleurs par des clés `restrict`.
 *
 * Aucune liste de statuts ici : le statut reste l'affaire du `StatusMachine`.
 */
final class OrderContentEngagement
{
    /**
     * @param  array<int, string>  $orderIds
     * @return array<string, true>
     */
    public function planned(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        return $this->index(DB::table('tour_stop_services')
            ->join('order_services', 'order_services.id', '=', 'tour_stop_services.order_service_id')
            ->whereIn('order_services.order_id', $orderIds)
            ->where('tour_stop_services.is_active_assignment', true)
            ->distinct()
            ->pluck('order_services.order_id'));
    }

    /**
     * @param  array<int, string>  $orderIds
     * @return array<string, true>
     */
    public function engaged(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $viaServices = fn (string $table) => DB::table($table)
            ->join('order_services', 'order_services.id', '=', "$table.order_service_id")
            ->whereIn('order_services.order_id', $orderIds)
            ->select('order_services.order_id');

        $query = $viaServices('tour_stop_services')
            ->union($viaServices('provider_settlement_lines'))
            ->union(DB::table('invoice_lines')->whereIn('order_id', $orderIds)->select('order_id'))
            ->union(DB::table('tour_period_assignments')
                ->join('packages', 'packages.id', '=', 'tour_period_assignments.package_id')
                ->whereIn('packages.order_id', $orderIds)
                ->select('packages.order_id'))
            ->union(DB::table('stock_reservations')
                ->join('order_lines', 'order_lines.id', '=', 'stock_reservations.order_line_id')
                ->whereIn('order_lines.order_id', $orderIds)
                ->select('order_lines.order_id'));

        return $this->index($query->pluck('order_id'));
    }

    /**
     * @param  iterable<int, string>  $ids
     * @return array<string, true>
     */
    private function index(iterable $ids): array
    {
        $set = [];

        foreach ($ids as $id) {
            $set[(string) $id] = true;
        }

        return $set;
    }
}
