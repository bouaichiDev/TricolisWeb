<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Tours\DTOs\UpdateTourStopServiceData;
use App\Modules\Tours\Exceptions\TourResourceStillInUse;
use App\Modules\Tours\Models\TourStopService;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modifie un service planifié.
 *
 * Passer `isActiveAssignment` à `false` est une **désactivation** : elle est
 * auditée sous `tour_stop_service.deactivated`, parce qu'elle décrit un
 * changement de plan et non une correction de saisie.
 *
 * Désactiver le dernier service actif d'un arrêt est refusé : la cardinalité
 * `1..*` laisserait un arrêt sans aucun service à rendre.
 */
final readonly class UpdateTourStopServiceAction
{
    public function __construct(
        private WriteAuditLog $audit,
        private RecalculateTourTotals $totals,
    ) {}

    public function execute(TourStopService $service, UpdateTourStopServiceData $data, AuditContext $context): TourStopService
    {
        $attributes = $data->attributes->all();

        if ($attributes === []) {
            return $service;
        }

        $this->assertKeepsOneActiveService($service, $attributes);

        $updated = DB::transaction(function () use ($service, $attributes, $context): TourStopService {
            $before = $service->only(array_keys($attributes));
            $service->update($attributes);
            $after = $service->fresh()->only(array_keys($attributes));

            if ($before !== $after) {
                $deactivated = ($before['is_active_assignment'] ?? null) === true
                    && ($after['is_active_assignment'] ?? null) === false;

                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    $deactivated ? 'tour_stop_service.deactivated' : 'tour_stop_service.updated',
                    $service,
                    $before,
                    $after,
                    null,
                    $context->ipAddress,
                );
            }

            return $service->fresh();
        });

        $this->totals->forStopService($updated);

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertKeepsOneActiveService(TourStopService $service, array $attributes): void
    {
        $becomesInactive = array_key_exists('is_active_assignment', $attributes)
            && $attributes['is_active_assignment'] === false
            && $service->is_active_assignment === true;

        if ($becomesInactive && $service->tourStop?->activeServiceCount() === 1) {
            throw TourResourceStillInUse::lastActiveService();
        }
    }
}
