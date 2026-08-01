<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Tours\Exceptions\TourResourceStillInUse;
use App\Modules\Tours\Models\TourPeriod;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Supprime une période.
 *
 * Refusée si elle porte encore des affectations. La cascade est déclarée en
 * base, mais l'effacer silencieusement ferait disparaître le lien entre un
 * service planifié et le moment où il devait être rendu — le §31 l'interdit.
 */
final readonly class DeleteTourPeriodAction
{
    public function __construct(
        private WriteAuditLog $audit,
        private RecalculateTourTotals $totals,
    ) {}

    public function execute(TourPeriod $period, AuditContext $context): void
    {
        if ($period->assignments()->exists()) {
            throw TourResourceStillInUse::periodHasAssignments();
        }

        $tour = $period->tour;

        DB::transaction(function () use ($period, $context): void {
            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'tour_period.deleted',
                $period,
                $period->only(['tour_id', 'tour_stop_id', 'period_type', 'sequence', 'status']),
                null,
                null,
                $context->ipAddress,
            );

            $period->delete();
        });

        if ($tour !== null) {
            $this->totals->execute($tour);
        }
    }
}
