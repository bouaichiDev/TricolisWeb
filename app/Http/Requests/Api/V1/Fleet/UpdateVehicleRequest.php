<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Fleet;

use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Providers\Models\Provider;
use App\Shared\Http\Rules\BelongsToActiveOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $vehicle = $this->route('vehicle');
        $providerId = $this->input('providerId', $vehicle?->provider_id);

        return [
            'legacyId' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'providerId' => [
                'sometimes', 'ulid',
                new BelongsToActiveOrganization(Provider::class, null, 'Ce fournisseur n’appartient pas à l’organisation active.'),
            ],
            'vehicleTypeId' => [
                'sometimes', 'ulid',
                new BelongsToActiveOrganization(VehicleType::class, null, 'Ce type de véhicule n’appartient pas à l’organisation active.'),
            ],
            'code' => [
                'sometimes', 'string', 'max:64',
                Rule::unique('vehicles', 'code')->where('provider_id', $providerId)->ignore($vehicle?->id),
            ],
            'registrationNumber' => [
                'sometimes', 'string', 'max:32',
                Rule::unique('vehicles', 'registration_number')->ignore($vehicle?->id),
            ],
            'payloadCapacity' => ['sometimes', 'numeric', 'min:0'],
            'volumeCapacity' => ['sometimes', 'numeric', 'min:0'],
            'palletCapacity' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'string', 'max:32'],
        ];
    }
}
