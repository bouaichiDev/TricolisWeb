<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Provider;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Modules\ProviderPortal\Services\ProviderStatusLabels;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un véhicule, tel que son fournisseur le voit, avec les actions offertes.
 *
 * @mixin Vehicle
 */
class ProviderVehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canUpdate = app(CurrentProviderContext::class)->can('provider.vehicles.update');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'registrationNumber' => $this->registration_number,
            'vehicleType' => $this->vehicleType === null ? null : ['id' => $this->vehicleType->id, 'name' => $this->vehicleType->name],
            'payloadCapacity' => (string) $this->payload_capacity,
            'volumeCapacity' => (string) $this->volume_capacity,
            'palletCapacity' => $this->pallet_capacity,
            'status' => $this->status,
            'statusLabel' => app(ProviderStatusLabels::class)->label('vehicle', $this->status),
            'actions' => [
                'canUpdate' => $canUpdate,
                'canDeactivate' => $canUpdate && $this->status === 'active',
                'canActivate' => $canUpdate && $this->status === 'inactive',
            ],
        ];
    }
}
