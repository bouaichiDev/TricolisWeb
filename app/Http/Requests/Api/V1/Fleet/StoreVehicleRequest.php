<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Fleet;

use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Providers\Models\Provider;
use App\Shared\Http\Rules\BelongsToActiveOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un véhicule.
 *
 * L'immatriculation est unique sur toute la plateforme : une plaque identifie
 * un véhicule physique.
 */
class StoreVehicleRequest extends FormRequest
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
        return [
            'legacyId' => ['nullable', 'integer', 'min:0'],
            'providerId' => [
                'required', 'ulid',
                new BelongsToActiveOrganization(Provider::class, null, 'Ce fournisseur n’appartient pas à l’organisation active.'),
            ],
            'vehicleTypeId' => [
                'required', 'ulid',
                new BelongsToActiveOrganization(VehicleType::class, null, 'Ce type de véhicule n’appartient pas à l’organisation active.'),
            ],
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('vehicles', 'code')->where('provider_id', $this->input('providerId')),
            ],
            'registrationNumber' => ['required', 'string', 'max:32', Rule::unique('vehicles', 'registration_number')],
            'payloadCapacity' => ['required', 'numeric', 'min:0'],
            'volumeCapacity' => ['required', 'numeric', 'min:0'],
            'palletCapacity' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', 'max:32'],
        ];
    }
}
