<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider\Resources;

use App\Http\Requests\Api\V1\Provider\ProviderServerControlledFields;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nouveau véhicule du fournisseur courant. Le type est vérifié par
 * `CreateVehicleAction` dans le référentiel `vehicle` de l'organisation ; une
 * immatriculation déjà prise est refusée sans dire par qui.
 */
class StoreProviderVehicleRequest extends FormRequest
{
    public const array LIMITS = [
        'payloadCapacity' => ['numeric', 'min:0', 'max:999999999.999'],
        'volumeCapacity' => ['numeric', 'min:0', 'max:99999999.9999'],
        'palletCapacity' => ['integer', 'min:0', 'max:10000'],
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentProviderContext::class)->organizationId();

        return [
            ...ProviderServerControlledFields::prohibit(),
            'code' => ['required', 'string', 'max:64', Rule::unique('vehicles', 'code')->where('organization_id', $organizationId)],
            'registrationNumber' => ['required', 'string', 'max:32', Rule::unique('vehicles', 'registration_number')],
            'vehicleTypeId' => ['required', 'string', 'ulid'],
            'payloadCapacity' => ['required', ...self::LIMITS['payloadCapacity']],
            'volumeCapacity' => ['required', ...self::LIMITS['volumeCapacity']],
            'palletCapacity' => ['required', ...self::LIMITS['palletCapacity']],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Ce code ne peut pas être utilisé.',
            'registrationNumber.unique' => 'Cette immatriculation ne peut pas être utilisée.',
        ];
    }
}
