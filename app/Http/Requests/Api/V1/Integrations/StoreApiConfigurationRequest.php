<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Integrations;

use App\Modules\Integrations\Services\ApiPermissionValidator;
use App\Shared\Http\Rules\IpOrCidr;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un accès API client.
 *
 * **`apiKey` et `apiKeyHash` ne sont pas acceptés** : la clé est générée par
 * l'Action. Les accepter permettrait à un appelant d'installer une clé qu'il a
 * choisie — donc éventuellement faible, ou connue d'un tiers.
 *
 * Les permissions sont validées contre la table `permissions` : le §15 interdit
 * de créer un second système de permissions.
 */
class StoreApiConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $customer = $this->route('customer');

        if ($customer !== null) {
            $this->merge(['customerId' => $customer->id]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $allowed = app(ApiPermissionValidator::class)->allowedCodes();

        return [
            'customerId' => ['required', 'ulid'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('customer_api_configurations', 'name')
                    ->where('customer_id', $this->input('customerId')),
            ],
            'allowedIps' => ['nullable', 'array', 'max:100'],
            'allowedIps.*' => ['required', new IpOrCidr],
            'permissions' => ['nullable', 'array', 'max:200'],
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
            'name.unique' => 'Ce nom d’accès existe déjà chez ce client.',
            'permissions.*.in' => 'Cette permission est inconnue ou interdite à une clé API client.',
        ];
    }
}
