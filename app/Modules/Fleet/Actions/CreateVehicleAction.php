<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Fleet\DTOs\CreateVehicleData;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Providers\Services\ProviderScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Crée un véhicule chez un fournisseur de l'organisation active.
 *
 * Invariant du §15 : le fournisseur et le type de véhicule doivent appartenir
 * à la même organisation. Les deux étant vérifiés séparément contre
 * l'organisation active, l'égalité est garantie ; elle est tout de même
 * revérifiée explicitement pour rester vraie si l'un des contrôles évolue.
 */
final readonly class CreateVehicleAction
{
    public function __construct(
        private ProviderScopeGuard $guard,
        private WriteAuditLog $audit,
    ) {}

    public function execute(CreateVehicleData $data, AuditContext $context): Vehicle
    {
        $provider = $this->guard->provider($data->providerId, $context->organizationId);
        $vehicleType = $this->guard->vehicleType($data->vehicleTypeId, $context->organizationId);
        $this->guard->assertSameOrganization($provider, $vehicleType);

        return DB::transaction(function () use ($data, $provider, $context): Vehicle {
            $vehicle = $provider->vehicles()->create($data->toAttributes());

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'vehicle.created',
                $vehicle,
                null,
                $vehicle->only(['provider_id', 'vehicle_type_id', 'code', 'registration_number', 'status']),
                null,
                $context->ipAddress,
            );

            return $vehicle;
        });
    }
}
