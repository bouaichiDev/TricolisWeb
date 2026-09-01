<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Templates;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Templates\Enums\TemplateType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Création d'un modèle.
 *
 * `subjectTemplate` n'est exigé que pour le canal e-mail : le §19 interdit de
 * l'imposer à SMS et WhatsApp, qui n'ont pas d'objet.
 *
 * `channel` est facultatif parce qu'un modèle de facture est un **document** :
 * il n'a ni canal, ni objet, ni destinataire. Les deux natures ne sont pas
 * laissées au hasard — la vérification croisée ci-dessous refuse un canal sur
 * une facture, et exige un canal partout ailleurs.
 *
 * L'unicité du code est vérifiée **dans le périmètre de l'organisation active**,
 * pas globalement : deux transporteurs peuvent nommer leur modèle de la même
 * façon.
 */
class StoreTemplateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = $this->header('X-Organization-Id');

        return [
            'customerId' => ['sometimes', 'nullable', 'ulid'],
            'serviceId' => ['sometimes', 'nullable', 'ulid'],
            'code' => [
                'required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('templates', 'code')
                    ->where(fn ($query) => $query->where('organization_id', $organizationId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['sometimes', 'nullable', Rule::in(CommunicationChannel::values())],
            'templateType' => ['required', Rule::in(TemplateType::values())],
            'subjectTemplate' => ['nullable', 'string', 'max:65535'],
            'bodyTemplate' => ['required', 'string'],
            // Un e-mail se redige souvent en HTML ; un SMS ne peut etre que
            // du texte. Le serveur doit savoir s'il echappe le corps.
            'bodyFormat' => ['sometimes', 'string', Rule::in(['text', 'html'])],
            'language' => ['required', 'string', 'max:10'],
            'availableVariables' => ['sometimes', 'nullable', 'array'],
            'isDefault' => ['sometimes', 'boolean'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            TemplateNature::check($validator, $this->input('templateType'), $this->input('channel'), $this->input('subjectTemplate'));
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Le code ne peut contenir que lettres, chiffres, points, tirets et tirets bas.',
            'code.unique' => 'Ce code est déjà utilisé par un autre modèle de cette organisation.',
        ];
    }
}
