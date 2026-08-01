<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourPeriodAssignment;
use App\Modules\Tours\Models\TourStopService;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Supprime une tournée et tout son agrégat.
 *
 * Les cascades déclarées en base ne suffisent pas. `tour_period_assignments`
 * référence `tour_stop_services` en `RESTRICT` : si MySQL choisissait de
 * supprimer les services avant les affectations, la contrainte bloquerait la
 * suppression sur une erreur SQL brute. L'ordre est donc imposé ici, dans une
 * transaction :
 *
 * ```text
 * affectations → périodes → services → arrêts → tournée
 * ```
 *
 * Les cascades restent déclarées comme filet de sécurité, jamais comme
 * mécanisme nominal.
 */
final readonly class DeleteTourAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(Tour $tour, AuditContext $context): void
    {
        DB::transaction(function () use ($tour, $context): void {
            $stopIds = $tour->stops()->pluck('id');
            $periodIds = $tour->periods()->pluck('id');
            $serviceIds = TourStopService::whereIn('tour_stop_id', $stopIds)->pluck('id');

            TourPeriodAssignment::whereIn('tour_period_id', $periodIds)
                ->orWhereIn('tour_stop_service_id', $serviceIds)
                ->delete();

            $tour->periods()->delete();
            TourStopService::whereIn('tour_stop_id', $stopIds)->delete();
            $tour->stops()->delete();

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'tour.deleted',
                $tour,
                $tour->only(['tour_number', 'tour_date', 'agency_id', 'status']),
                null,
                null,
                $context->ipAddress,
            );

            $tour->delete();
        });
    }
}
