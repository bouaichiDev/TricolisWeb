<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider\Resources;

use App\Http\Requests\Api\V1\Provider\ProviderServerControlledFields;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Modules\ProviderPortal\Services\ProviderResourceScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'un véhicule du fournisseur courant ; le statut a ses routes.
 */
class UpdateProviderVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Le véhicule est résolu dans le périmètre **avant** la validation : sans
     * cela, un identifiant étranger confronté aux codes pris dirait à qui
     * appartient quelle immatriculation. Étranger ou inexistant : 404.
     */
    protected function prepareForValidation(): void
    {
        app(ProviderResourceScope::class)->vehicle((string) $this->route('vehicle'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentProviderContext::class)->organizationId();
        $id = (string) $this->route('vehicle');
        $limits = StoreProviderVehicleRequest::LIMITS;

        return [
            ...ProviderServerControlledFields::prohibit(),
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('vehicles', 'code')->where('organization_id', $organizationId)->ignore($id)],
            'registrationNumber' => ['sometimes', 'string', 'max:32', Rule::unique('vehicles', 'registration_number')->ignore($id)],
            'vehicleTypeId' => ['sometimes', 'string', 'ulid'],
            'payloadCapacity' => ['sometimes', ...$limits['payloadCapacity']],
            'volumeCapacity' => ['sometimes', ...$limits['volumeCapacity']],
            'palletCapacity' => ['sometimes', ...$limits['palletCapacity']],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return (new StoreProviderVehicleRequest)->messages();
    }
}
