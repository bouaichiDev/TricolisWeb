<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Communications;

use App\Modules\Communications\Enums\CommunicationEventType;
use App\Modules\Communications\Enums\RecipientRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une règle de communication.
 *
 * `templateId` n'est pas validé par un simple `exists` : l'appartenance à
 * l'organisation active est vérifiée par `CommunicationScopeGuard`, dans
 * l'Action. Un `exists` seul laisserait rattacher un modèle d'un autre
 * transporteur.
 *
 * `delayUnit` reste une chaîne — le §17 interdit l'enum — mais elle est
 * restreinte aux unités que le moteur technique sait ajouter.
 */
class StoreCommunicationRuleRequest extends FormRequest
{
    /** @var list<string> */
    public const array DELAY_UNITS = ['minutes', 'hours', 'days'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'serviceId' => ['sometimes', 'nullable', 'ulid'],
            'templateId' => ['required', 'ulid'],
            'eventType' => ['required', Rule::in(CommunicationEventType::values())],
            'recipientRole' => ['required', Rule::in(RecipientRole::values())],
            'delayValue' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'delayUnit' => ['required', Rule::in(self::DELAY_UNITS)],
            'conditions' => ['sometimes', 'nullable', 'array'],
            'isAutomatic' => ['sometimes', 'boolean'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'delayUnit.in' => 'Unité de délai non prise en charge : minutes, hours ou days.',
            'delayValue.min' => 'Un délai négatif n’a pas de sens : il ferait envoyer avant l’événement.',
        ];
    }
}
