<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Fleet\DTOs\UpdateVehicleData;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Providers\Services\ProviderScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modifie un véhicule.
 *
 * Réaffecter un véhicule ou changer son type repasse par les mêmes contrôles
 * qu'à la création, y compris la cohérence organisation ↔ type.
 */
final readonly class UpdateVehicleAction
{
    public function __construct(
        private ProviderScopeGuard $guard,
        private WriteAuditLog $audit,
    ) {}

    public function execute(Vehicle $vehicle, UpdateVehicleData $data, AuditContext $context): Vehicle
    {
        $attributes = $data->attributes->all();

        if ($attributes === []) {
            return $vehicle;
        }

        $provider = $data->attributes->has('provider_id')
            ? $this->guard->provider($data->attributes->get('provider_id'), $context->organizationId)
            : $vehicle->provider;

        if ($data->attributes->has('vehicle_type_id')) {
            $type = $this->guard->vehicleType($data->attributes->get('vehicle_type_id'), $context->organizationId);
            $this->guard->assertSameOrganization($provider, $type);
        }

        return DB::transaction(function () use ($vehicle, $attributes, $context): Vehicle {
            $before = $vehicle->only(array_keys($attributes));
            $vehicle->update($attributes);
            $after = $vehicle->fresh()->only(array_keys($attributes));

            if ($before !== $after) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'vehicle.updated',
                    $vehicle,
                    $before,
                    $after,
                    null,
                    $context->ipAddress,
                );
            }

            return $vehicle->fresh();
        });
    }
}
