<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Statuses;

use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Models\StatusPropagation;
use App\Modules\Statuses\Services\StatusRelations;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'une règle de propagation.
 *
 * Tout se corrige, y compris la paire : une règle mal visée — le bon statut sur
 * la mauvaise entité — se répare, elle ne se supprime pas pour être resaisie.
 * Les mêmes garde-fous qu'à la création s'appliquent donc : les deux entités
 * doivent être reliées, et la paire rester unique.
 */
class UpdateStatusPropagationRequest extends FormRequest
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
        /** @var StatusPropagation $rule */
        $rule = $this->route('statusPropagation');

        return [
            'fromStatusId' => ['sometimes', 'ulid', 'exists:statuses,id'],
            'toStatusId' => [
                'sometimes', 'ulid', 'exists:statuses,id', 'different:fromStatusId',
                Rule::unique('status_propagations', 'to_status_id')
                    ->where('from_status_id', $this->from())
                    ->ignore($rule->id),
            ],
            'mode' => ['sometimes', Rule::in([StatusPropagation::MODE_ALL, StatusPropagation::MODE_ANY])],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = Status::find($this->from());
            $to = Status::find($this->to());

            if ($from === null || $to === null) {
                return;
            }

            if (! app(StatusRelations::class)->related($from->source, $to->source)) {
                $validator->errors()->add(
                    'toStatusId',
                    'Ces deux entités ne sont pas reliées : la propagation n’aurait aucun chemin à suivre.',
                );
            }
        });
    }

    /** Le statut de départ après modification : celui envoyé, sinon l'actuel. */
    private function from(): ?string
    {
        /** @var StatusPropagation $rule */
        $rule = $this->route('statusPropagation');

        return $this->input('fromStatusId', $rule->from_status_id);
    }

    private function to(): ?string
    {
        /** @var StatusPropagation $rule */
        $rule = $this->route('statusPropagation');

        return $this->input('toStatusId', $rule->to_status_id);
    }
}
