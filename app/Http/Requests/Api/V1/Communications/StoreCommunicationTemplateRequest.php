<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Communications;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Enums\CommunicationTemplateType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un modèle de message.
 *
 * `subjectTemplate` n'est exigé que pour le canal e-mail : le §11 interdit de
 * l'imposer à SMS et WhatsApp, qui n'ont pas d'objet.
 *
 * L'unicité du code est vérifiée **dans le périmètre de l'organisation active**,
 * pas globalement : deux transporteurs peuvent nommer leur modèle de la même
 * façon.
 */
class StoreCommunicationTemplateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = $this->header('X-Organization-Id');

        return [
            'serviceId' => ['sometimes', 'nullable', 'ulid'],
            'code' => [
                'required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('communication_templates', 'code')
                    ->where(fn ($query) => $query->where('organization_id', $organizationId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::in(CommunicationChannel::values())],
            'templateType' => ['required', Rule::in(CommunicationTemplateType::values())],
            'subjectTemplate' => ['nullable', 'string', 'max:65535', Rule::requiredIf(
                fn (): bool => $this->input('channel') === CommunicationChannel::EMAIL->value,
            )],
            'bodyTemplate' => ['required', 'string'],
            'language' => ['required', 'string', 'max:10'],
            'availableVariables' => ['sometimes', 'nullable', 'array'],
            'isDefault' => ['sometimes', 'boolean'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Le code ne peut contenir que lettres, chiffres, points, tirets et tirets bas.',
            'code.unique' => 'Ce code est déjà utilisé par un autre modèle de cette organisation.',
            'subjectTemplate.required' => 'Le canal e-mail exige un objet.',
        ];
    }
}
