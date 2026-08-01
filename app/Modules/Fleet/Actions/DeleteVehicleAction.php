<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Fleet\Models\Vehicle;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Supprime un véhicule.
 *
 * Le refus de suppression d'un véhicule référencé par une tournée est prévu par
 * le cahier des charges mais reste sans objet : le module Tours n'existe pas
 * encore. Il devra être ajouté avec la phase Planification.
 */
final readonly class DeleteVehicleAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(Vehicle $vehicle, AuditContext $context): void
    {
        DB::transaction(function () use ($vehicle, $context): void {
            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'vehicle.deleted',
                $vehicle,
                $vehicle->only(['provider_id', 'vehicle_type_id', 'code', 'registration_number', 'status']),
                null,
                null,
                $context->ipAddress,
            );

            $vehicle->delete();
        });
    }
}
