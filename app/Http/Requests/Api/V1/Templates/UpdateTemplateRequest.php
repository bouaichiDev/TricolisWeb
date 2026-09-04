<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Templates;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Templates\Enums\TemplateType;
use App\Modules\Templates\Models\Template;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Modification partielle d'un modèle.
 *
 * `code` est absent : il identifie le modèle auprès des intégrations, et le
 * déplacer romprait leurs références. Le recréer est explicite ; le renommer ne
 * l'est pas.
 *
 * La vérification de nature porte sur l'état **résultant**, pas sur la seule
 * charge utile : passer un modèle d'e-mail en facture sans envoyer `channel`
 * lui laisserait son ancien canal, et produirait exactement la facture avec
 * `channel = EMAIL` que le §0.7 interdit.
 */
class UpdateTemplateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customerId' => ['sometimes', 'nullable', 'ulid'],
            'serviceId' => ['sometimes', 'nullable', 'ulid'],
            'name' => ['sometimes', 'string', 'max:255'],
            'channel' => ['sometimes', 'nullable', Rule::in(CommunicationChannel::values())],
            'templateType' => ['sometimes', Rule::in(TemplateType::values())],
            'subjectTemplate' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'bodyTemplate' => ['sometimes', 'string'],
            // Un e-mail se redige souvent en HTML ; un SMS ne peut etre que
            // du texte. Le serveur doit savoir s'il echappe le corps.
            'bodyFormat' => ['sometimes', 'string', Rule::in(['text', 'html'])],
            'language' => ['sometimes', 'string', 'max:10'],
            'availableVariables' => ['sometimes', 'nullable', 'array'],
            'isDefault' => ['sometimes', 'boolean'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $template = $this->route('template');

            if (! $template instanceof Template) {
                return;
            }

            TemplateNature::check(
                $validator,
                $this->has('templateType') ? $this->input('templateType') : $template->template_type?->value,
                $this->has('channel') ? $this->input('channel') : $template->channel?->value,
                $this->has('subjectTemplate') ? $this->input('subjectTemplate') : $template->subject_template,
            );
        });
    }
}
