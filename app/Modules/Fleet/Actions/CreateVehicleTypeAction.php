<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Fleet\DTOs\CreateVehicleTypeData;
use App\Modules\Fleet\Models\VehicleType;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Crée un type de véhicule dans l'organisation active.
 */
final readonly class CreateVehicleTypeAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(CreateVehicleTypeData $data, AuditContext $context): VehicleType
    {
        return DB::transaction(function () use ($data, $context): VehicleType {
            $type = VehicleType::create($data->toAttributes($context->organizationId));

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'vehicle_type.created',
                $type,
                null,
                $type->only(['code', 'name', 'status']),
                null,
                $context->ipAddress,
            );

            return $type;
        });
    }
}
