<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Integrations;

use App\Modules\Integrations\Services\ApiPermissionValidator;
use App\Shared\Http\Rules\IpOrCidr;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'un accès API.
 *
 * Ni la clé, ni son empreinte, ni `lastUsedAt` : la première se renouvelle par
 * `POST /rotate-key`, la seconde est posée par la vérification de clé.
 */
class UpdateApiConfigurationRequest extends FormRequest
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
        $configuration = $this->route('configuration');
        $allowed = app(ApiPermissionValidator::class)->allowedCodes();

        return [
            'name' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('customer_api_configurations', 'name')
                    ->where('customer_id', $configuration?->customer_id)
                    ->ignore($configuration?->id),
            ],
            'allowedIps' => ['sometimes', 'nullable', 'array', 'max:100'],
            'allowedIps.*' => ['required', new IpOrCidr],
            'permissions' => ['sometimes', 'nullable', 'array', 'max:200'],
            'permissions.*' => ['required', 'string', Rule::in($allowed)],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.*.in' => 'Cette permission est inconnue ou interdite à une clé API client.',
        ];
    }
}
