<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Communications;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Enums\CommunicationTemplateType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification partielle d'un modèle de message.
 *
 * `code` est absent : il identifie le modèle auprès des intégrations, et le
 * déplacer romprait leurs références. Le recréer est explicite ; le renommer ne
 * l'est pas.
 */
class UpdateCommunicationTemplateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'serviceId' => ['sometimes', 'nullable', 'ulid'],
            'name' => ['sometimes', 'string', 'max:255'],
            'channel' => ['sometimes', Rule::in(CommunicationChannel::values())],
            'templateType' => ['sometimes', Rule::in(CommunicationTemplateType::values())],
            'subjectTemplate' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'bodyTemplate' => ['sometimes', 'string'],
            'language' => ['sometimes', 'string', 'max:10'],
            'availableVariables' => ['sometimes', 'nullable', 'array'],
            'isDefault' => ['sometimes', 'boolean'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
