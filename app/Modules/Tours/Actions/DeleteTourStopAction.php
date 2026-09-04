<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Planning\Jobs\RecalculateTourRouteJob;
use App\Modules\Tours\Exceptions\TourResourceStillInUse;
use App\Modules\Tours\Models\TourPeriodAssignment;
use App\Modules\Tours\Models\TourStop;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Supprime un arrêt et les services qu'il porte.
 *
 * Refusé si des périodes le référencent encore : la clé étrangère est en
 * `SET NULL` pour que la suppression d'une tournée reste exécutable, mais
 * délier silencieusement une période de son arrêt perdrait l'information. Le
 * refus métier arrive donc avant SQL.
 *
 * Les services de l'arrêt disparaissent avec lui — c'est la composition
 * `TourStop *-- TourStopService`. Ils sont supprimés explicitement pour que
 * leurs affectations soient contrôlées d'abord.
 */
final readonly class DeleteTourStopAction
{
    public function __construct(
        private WriteAuditLog $audit,
        private RecalculateTourTotals $totals,
    ) {}

    public function execute(TourStop $stop, AuditContext $context): void
    {
        if ($stop->periods()->exists()) {
            throw TourResourceStillInUse::stopHasPeriods();
        }

        $serviceIds = $stop->services()->pluck('id');

        if (TourPeriodAssignment::whereIn('tour_stop_service_id', $serviceIds)->exists()) {
            throw TourResourceStillInUse::serviceHasAssignments();
        }

        $tour = $stop->tour;

        DB::transaction(function () use ($stop, $context): void {
            $stop->services()->delete();

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'tour_stop.deleted',
                $stop,
                $stop->only(['tour_id', 'address_id', 'sequence', 'status']),
                null,
                null,
                $context->ipAddress,
            );

            $stop->delete();
        });

        if ($tour !== null) {
            $this->totals->execute($tour);
            // La geometrie de la tournee a change : l'itineraire connu decrit
            // un ordre qui n'existe plus. Le calcul part en file, apres la
            // transaction, pour ne pas faire attendre le geste.
            RecalculateTourRouteJob::dispatch($tour->id)->afterCommit();
        }
    }
}
