<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Providers;

use App\Shared\Organizations\CurrentOrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'un fournisseur.
 *
 * L'organisation n'est pas modifiable : déplacer un fournisseur d'une
 * organisation à l'autre emporterait ses chauffeurs et ses véhicules hors de
 * leur périmètre.
 */
class UpdateProviderRequest extends FormRequest
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
        $providerId = $this->route('provider')?->id;

        return [
            'legacyId' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'code' => [
                'sometimes', 'string', 'max:64',
                Rule::unique('providers', 'code')->where('organization_id', $organizationId)->ignore($providerId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'providerType' => ['sometimes', 'string', 'max:64'],
            'status' => ['sometimes', 'string', 'max:32'],
        ];
    }
}
