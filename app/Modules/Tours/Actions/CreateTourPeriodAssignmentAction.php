<?php

declare(strict_types=1);

namespace App\Modules\Tours\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Tours\DTOs\CreateTourPeriodAssignmentData;
use App\Modules\Tours\Models\TourPeriod;
use App\Modules\Tours\Models\TourPeriodAssignment;
use App\Modules\Tours\Services\AssignmentConsistency;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Affecte un service planifié — et éventuellement un colis — à une période.
 */
final readonly class CreateTourPeriodAssignmentAction
{
    public function __construct(
        private AssignmentConsistency $consistency,
        private WriteAuditLog $audit,
    ) {}

    public function execute(TourPeriod $period, CreateTourPeriodAssignmentData $data, AuditContext $context): TourPeriodAssignment
    {
        $service = $this->consistency->service($period, $data->tourStopServiceId);
        $this->consistency->package($service, $data->packageId);
        $this->consistency->notDuplicated($period, $service->id, $data->packageId);

        return DB::transaction(function () use ($period, $data, $context): TourPeriodAssignment {
            $assignment = TourPeriodAssignment::create($data->toAttributes($period->id));

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'tour_period_assignment.created',
                $assignment,
                null,
                $assignment->only(['tour_period_id', 'tour_stop_service_id', 'package_id']),
                null,
                $context->ipAddress,
            );

            return $assignment;
        });
    }
}
