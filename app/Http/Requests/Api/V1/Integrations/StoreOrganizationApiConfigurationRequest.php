<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Integrations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Déclaration d'une API externe appelée par l'organisme.
 *
 * À ne pas confondre avec `StoreApiConfigurationRequest`, qui déclare l'accès
 * d'un **client** à notre API. Ici, c'est nous qui appelons.
 *
 * `baseUrl` est contrainte à `https` : un secret d'authentification envoyé en
 * clair sur le réseau ne serait plus un secret.
 */
class StoreOrganizationApiConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'baseUrl' => ['required', 'url:https', 'max:512'],
            'authType' => ['required', Rule::in(['none', 'bearer', 'api_key', 'basic'])],
            // Le secret n'est jamais relu : il ne peut qu'etre pose ou remplace.
            'credentials' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'headers' => ['sometimes', 'nullable', 'array'],
            'timeoutSeconds' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'baseUrl.url' => 'L’adresse doit être en HTTPS : un secret envoyé en clair n’en est plus un.',
        ];
    }
}
