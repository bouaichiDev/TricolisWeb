<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Tours\DTOs\UpdateTourPeriodAssignmentData;
use App\Modules\Tours\Models\TourPeriodAssignment;
use App\Modules\Tours\Services\AssignmentConsistency;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modifie une affectation.
 *
 * Les mêmes contrôles qu'à la création s'appliquent : changer le service ou le
 * colis peut faire sortir l'affectation de la tournée ou de la commande.
 */
final readonly class UpdateTourPeriodAssignmentAction
{
    public function __construct(
        private AssignmentConsistency $consistency,
        private WriteAuditLog $audit,
    ) {}

    public function execute(TourPeriodAssignment $assignment, UpdateTourPeriodAssignmentData $data, AuditContext $context): TourPeriodAssignment
    {
        $attributes = $data->attributes->all();

        if ($attributes === []) {
            return $assignment;
        }

        $period = $assignment->tourPeriod;
        $serviceId = $attributes['tour_stop_service_id'] ?? $assignment->tour_stop_service_id;
        $packageId = array_key_exists('package_id', $attributes)
            ? $attributes['package_id']
            : $assignment->package_id;

        $service = $this->consistency->service($period, $serviceId);
        $this->consistency->package($service, $packageId);
        $this->consistency->notDuplicated($period, $serviceId, $packageId, $assignment->id);

        return DB::transaction(function () use ($assignment, $attributes, $context): TourPeriodAssignment {
            $before = $assignment->only(array_keys($attributes));
            $assignment->update($attributes);
            $after = $assignment->fresh()->only(array_keys($attributes));

            if ($before !== $after) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'tour_period_assignment.updated',
                    $assignment,
                    $before,
                    $after,
                    null,
                    $context->ipAddress,
                );
            }

            return $assignment->fresh();
        });
    }
}
