<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Fleet;

use App\Shared\Organizations\CurrentOrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleTypeRequest extends FormRequest
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
        $organizationId = app(CurrentOrganizationContext::class)->getOrganizationId();
        $vehicleTypeId = $this->route('vehicleType')?->id;

        return [
            'code' => [
                'sometimes', 'string', 'max:64',
                Rule::unique('vehicle_types', 'code')->where('organization_id', $organizationId)->ignore($vehicleTypeId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:32'],
        ];
    }
}
