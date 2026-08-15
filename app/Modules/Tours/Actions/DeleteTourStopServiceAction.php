<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Tours\Exceptions\TourResourceStillInUse;
use App\Modules\Tours\Models\TourStopService;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Supprime un service planifié.
 *
 * Deux refus, tous deux exigés par le modèle :
 *
 * - le service est affecté à une période — le §14 l'interdit, et la clé
 *   étrangère est en `RESTRICT` ;
 * - le service est le dernier actif de son arrêt — la cardinalité `1..*`
 *   laisserait un arrêt sans service. Pour retirer le dernier, il faut
 *   supprimer l'arrêt.
 *
 * Le §13 privilégie par ailleurs la désactivation à la suppression : une
 * affectation remplacée se désactive, elle ne s'efface pas.
 */
final readonly class DeleteTourStopServiceAction
{
    public function __construct(
        private WriteAuditLog $audit,
        private RecalculateTourTotals $totals,
    ) {}

    public function execute(TourStopService $service, AuditContext $context): void
    {
        if ($service->assignments()->exists()) {
            throw TourResourceStillInUse::serviceHasAssignments();
        }

        if ($service->is_active_assignment && $service->tourStop?->activeServiceCount() === 1) {
            throw TourResourceStillInUse::lastActiveService();
        }

        $tour = $service->tourStop?->tour;

        DB::transaction(function () use ($service, $context): void {
            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'tour_stop_service.deleted',
                $service,
                $service->only(['tour_stop_id', 'order_service_id', 'sequence_within_stop', 'status']),
                null,
                null,
                $context->ipAddress,
            );

            $service->delete();
        });

        if ($tour !== null) {
            $this->totals->execute($tour);
        }
    }
}
