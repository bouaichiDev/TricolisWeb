<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Integrations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateImportConfigurationRequest extends FormRequest
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

        return [
            'name' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('customer_import_configurations', 'name')
                    ->where('customer_id', $configuration?->customer_id)
                    ->ignore($configuration?->id),
            ],
            'sourceType' => ['sometimes', 'string', 'max:64'],
            'fileFormat' => ['sometimes', 'string', 'max:32'],
            'mapping' => ['sometimes', 'nullable', 'array', 'max:500'],
            'validationRules' => ['sometimes', 'nullable', 'array', 'max:500'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
