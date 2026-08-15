<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Fleet\DTOs\UpdateVehicleTypeData;
use App\Modules\Fleet\Models\VehicleType;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modifie un type de véhicule.
 *
 * Les véhicules déjà rattachés ne sont pas touchés : seul le libellé du
 * référentiel change.
 */
final readonly class UpdateVehicleTypeAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(VehicleType $vehicleType, UpdateVehicleTypeData $data, AuditContext $context): VehicleType
    {
        $attributes = $data->attributes->all();

        if ($attributes === []) {
            return $vehicleType;
        }

        return DB::transaction(function () use ($vehicleType, $attributes, $context): VehicleType {
            $before = $vehicleType->only(array_keys($attributes));
            $vehicleType->update($attributes);
            $after = $vehicleType->fresh()->only(array_keys($attributes));

            if ($before !== $after) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'vehicle_type.updated',
                    $vehicleType,
                    $before,
                    $after,
                    null,
                    $context->ipAddress,
                );
            }

            return $vehicleType->fresh();
        });
    }
}
