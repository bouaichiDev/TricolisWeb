<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Integrations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une configuration d'import.
 *
 * `mapping` et `validationRules` sont validés comme **tableaux**, sans schéma
 * imposé : le diagramme n'en définit pas, et le §9 interdit de l'inventer. Ce
 * sont des données, jamais évaluées.
 */
class StoreImportConfigurationRequest extends FormRequest
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
        return [
            'customerId' => ['required', 'ulid'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('customer_import_configurations', 'name')
                    ->where('customer_id', $this->input('customerId')),
            ],
            'sourceType' => ['required', 'string', 'max:64'],
            'fileFormat' => ['required', 'string', 'max:32'],
            'mapping' => ['nullable', 'array', 'max:500'],
            'validationRules' => ['nullable', 'array', 'max:500'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.unique' => 'Ce nom de configuration existe déjà chez ce client.'];
    }
}
