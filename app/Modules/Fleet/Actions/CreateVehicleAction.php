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
 * Crée un véhicule de l'organisation active, fourni ou non par un tiers.
 *
 * **Le fournisseur est facultatif** : un transporteur possède ses propres
 * camions. L'organisation vient du contexte actif, pas du fournisseur, qui peut
 * manquer.
 *
 * Invariant du §15 quand un fournisseur est donné : lui et le type de véhicule
 * doivent appartenir à la même organisation. Les deux étant vérifiés
 * séparément contre l'organisation active, l'égalité est garantie ; elle est
 * tout de même revérifiée pour rester vraie si l'un des contrôles évolue.
 */
final readonly class CreateVehicleAction
{
    public function __construct(
        private ProviderScopeGuard $guard,
        private WriteAuditLog $audit,
    ) {}

    public function execute(CreateVehicleData $data, AuditContext $context): Vehicle
    {
        $vehicleType = $this->guard->vehicleType($data->vehicleTypeId, $context->organizationId);

        // Le fournisseur est facultatif : le transporteur possede ses propres
        // camions. Quand il est donne, il doit etre de la meme organisation que
        // le type — l'invariant du §15 tient sur ce qui existe.
        if ($data->providerId !== null) {
            $provider = $this->guard->provider($data->providerId, $context->organizationId);
            $this->guard->assertSameOrganization($provider, $vehicleType);
        }

        return DB::transaction(function () use ($data, $context): Vehicle {
            $vehicle = Vehicle::create($data->toAttributes($context->organizationId));

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'vehicle.created',
                $vehicle,
                null,
                $vehicle->only(['organization_id', 'provider_id', 'vehicle_type_id', 'code', 'registration_number', 'status']),
                null,
                $context->ipAddress,
            );

            return $vehicle;
        });
    }
}
