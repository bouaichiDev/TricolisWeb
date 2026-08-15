<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Communications;

use App\Modules\Communications\Enums\CommunicationEventType;
use App\Modules\Communications\Enums\RecipientRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification partielle d'une règle de communication.
 */
class UpdateCommunicationRuleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'serviceId' => ['sometimes', 'nullable', 'ulid'],
            'templateId' => ['sometimes', 'ulid'],
            'eventType' => ['sometimes', Rule::in(CommunicationEventType::values())],
            'recipientRole' => ['sometimes', Rule::in(RecipientRole::values())],
            'delayValue' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'delayUnit' => ['sometimes', Rule::in(StoreCommunicationRuleRequest::DELAY_UNITS)],
            'conditions' => ['sometimes', 'nullable', 'array'],
            'isAutomatic' => ['sometimes', 'boolean'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
