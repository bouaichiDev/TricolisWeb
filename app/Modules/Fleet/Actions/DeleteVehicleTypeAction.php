<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Fleet\Exceptions\VehicleTypeStillInUse;
use App\Modules\Fleet\Models\VehicleType;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Supprime un type de véhicule inutilisé.
 *
 * Supprimer un type ne doit jamais supprimer les véhicules qui l'utilisent :
 * la contrainte SQL est en `RESTRICT`, ce contrôle la précède avec un message
 * métier.
 */
final readonly class DeleteVehicleTypeAction
{
    public function __construct(private WriteAuditLog $audit) {}

    /**
     * @throws VehicleTypeStillInUse
     */
    public function execute(VehicleType $vehicleType, AuditContext $context): void
    {
        if ($vehicleType->vehicles()->exists()) {
            throw VehicleTypeStillInUse::hasVehicles();
        }

        DB::transaction(function () use ($vehicleType, $context): void {
            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'vehicle_type.deleted',
                $vehicleType,
                $vehicleType->only(['code', 'name', 'status']),
                null,
                null,
                $context->ipAddress,
            );

            $vehicleType->delete();
        });
    }
}
