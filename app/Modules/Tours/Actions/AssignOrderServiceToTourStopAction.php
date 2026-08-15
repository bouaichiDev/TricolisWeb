<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Tours\DTOs\CreateTourStopServiceData;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;
use App\Modules\Tours\Services\TourScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Planifie un service de commande sur un arrêt existant.
 *
 * Le service doit venir d'une commande de l'organisation de la tournée. Aucune
 * contrainte n'interdit de planifier deux fois le même `OrderService` : le
 * diagramme pose `OrderService "1" -- "0..*" TourStopService` sans restriction,
 * et l'historique des affectations repose précisément sur cette possibilité.
 */
final readonly class AssignOrderServiceToTourStopAction
{
    public function __construct(
        private TourScopeGuard $guard,
        private WriteAuditLog $audit,
        private RecalculateTourTotals $totals,
    ) {}

    public function execute(TourStop $stop, CreateTourStopServiceData $data, AuditContext $context): TourStopService
    {
        $tour = $stop->tour;

        $this->guard->orderService($data->orderServiceId, $tour->organization_id);

        $service = DB::transaction(function () use ($stop, $data, $context): TourStopService {
            $service = TourStopService::create($data->toAttributes($stop->id));

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'tour_stop_service.created',
                $service,
                null,
                $service->only(['tour_stop_id', 'order_service_id', 'sequence_within_stop', 'is_active_assignment', 'status']),
                null,
                $context->ipAddress,
            );

            return $service;
        });

        $this->totals->execute($tour);

        return $service;
    }
}
