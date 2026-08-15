<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Exports;

use App\Modules\Exports\Enums\ExportFormat;
use App\Modules\Exports\Enums\ExportTransport;
use App\Shared\Http\Rules\SafeFileNamePattern;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'une configuration d'export.
 *
 * `password` suit trois branches : absent, l'ancien est conservé ; renseigné,
 * il remplace ; `null`, il est effacé. C'est le DTO qui les distingue.
 */
class UpdateExportConfigurationRequest extends FormRequest
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
                Rule::unique('customer_export_configurations', 'name')
                    ->where('customer_id', $configuration?->customer_id)
                    ->ignore($configuration?->id),
            ],
            'exportType' => ['sometimes', 'string', 'max:64'],
            'format' => ['sometimes', Rule::enum(ExportFormat::class)],
            'transport' => ['sometimes', Rule::enum(ExportTransport::class)],
            'host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'port' => ['sometimes', 'nullable', 'integer', 'between:1,65535'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remoteDirectory' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fileNamePattern' => ['sometimes', 'nullable', 'string', 'max:255', new SafeFileNamePattern],
            'encoding' => ['sometimes', 'nullable', 'string', 'max:32'],
            'frequency' => ['sometimes', 'nullable', 'string', 'max:64'],
            'settings' => ['sometimes', 'nullable', 'array', 'max:500'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
