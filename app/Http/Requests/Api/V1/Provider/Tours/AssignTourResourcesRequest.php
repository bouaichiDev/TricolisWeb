<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider\Tours;

use App\Http\Requests\Api\V1\Provider\ProviderServerControlledFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Chauffeur et/ou véhicule d'une tournée ; `null` retire l'affectation. Rien
 * d'autre de la tournée ne se change depuis le portail.
 */
class AssignTourResourcesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...ProviderServerControlledFields::prohibit([
                ...ProviderServerControlledFields::TENANT,
                'tourDate', 'plannedStartAt', 'plannedEndAt', 'agencyId', 'depotId', 'instructions',
            ]),
            'driverId' => ['sometimes', 'nullable', 'string', 'ulid'],
            'vehicleId' => ['sometimes', 'nullable', 'string', 'ulid'],
        ];
    }
}
