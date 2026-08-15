<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Tours\Models\TourPeriodAssignment;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Supprime une affectation.
 *
 * Aucun refus : l'affectation est la feuille de l'agrégat, rien ne la
 * référence. C'est elle qui empêche de supprimer un service ou une période, pas
 * l'inverse.
 */
final readonly class DeleteTourPeriodAssignmentAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(TourPeriodAssignment $assignment, AuditContext $context): void
    {
        DB::transaction(function () use ($assignment, $context): void {
            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'tour_period_assignment.deleted',
                $assignment,
                $assignment->only(['tour_period_id', 'tour_stop_service_id', 'package_id']),
                null,
                null,
                $context->ipAddress,
            );

            $assignment->delete();
        });
    }
}
