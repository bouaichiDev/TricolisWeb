<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Exports;

use App\Modules\Exports\Enums\ExportFormat;
use App\Modules\Exports\Enums\ExportTransport;
use App\Shared\Http\Rules\SafeFileNamePattern;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une configuration d'export.
 *
 * `password` est reçu **en clair** et chiffré par le DTO. Il n'est jamais
 * restitué, ni journalisé.
 *
 * Seul `host` est conditionnellement obligatoire, selon le transport : le §19
 * dit que les autres champs « peuvent » être nécessaires — en faire des
 * obligations serait plus strict que le diagramme. Une connexion FTP anonyme,
 * sur port par défaut, à la racine, reste un cas réel.
 */
class StoreExportConfigurationRequest extends FormRequest
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
        $transportsNeedingHost = array_map(
            static fn (ExportTransport $t): string => $t->value,
            array_filter(ExportTransport::cases(), static fn (ExportTransport $t): bool => $t->requiresHost()),
        );

        return [
            'customerId' => ['required', 'ulid'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('customer_export_configurations', 'name')
                    ->where('customer_id', $this->input('customerId')),
            ],
            'exportType' => ['required', 'string', 'max:64'],
            'format' => ['required', Rule::enum(ExportFormat::class)],
            'transport' => ['required', Rule::enum(ExportTransport::class)],
            'host' => ['nullable', 'string', 'max:255', Rule::requiredIf(
                fn (): bool => in_array($this->input('transport'), $transportsNeedingHost, true),
            )],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'remoteDirectory' => ['nullable', 'string', 'max:255'],
            'fileNamePattern' => ['nullable', 'string', 'max:255', new SafeFileNamePattern],
            'encoding' => ['nullable', 'string', 'max:32'],
            'frequency' => ['nullable', 'string', 'max:64'],
            'settings' => ['nullable', 'array', 'max:500'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ce nom de configuration existe déjà chez ce client.',
            'host.required' => 'Ce transport exige un hôte distant.',
        ];
    }
}
