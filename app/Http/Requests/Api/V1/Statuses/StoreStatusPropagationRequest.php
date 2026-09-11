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
 * Déclaration d'une règle de propagation.
 *
 * La paire doit relier deux entités **voisines** — une commande et ses colis, un
 * service et ses colis. Deux entités sans lien n'auraient aucun chemin à
 * suivre : la règle serait enregistrée et ne ferait jamais rien, ce qui est pire
 * qu'un refus.
 */
class StoreStatusPropagationRequest extends FormRequest
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
        return [
            'fromStatusId' => ['required', 'ulid', 'exists:statuses,id'],
            'toStatusId' => [
                'required', 'ulid', 'exists:statuses,id', 'different:fromStatusId',
                Rule::unique('status_propagations', 'to_status_id')
                    ->where('from_status_id', $this->input('fromStatusId')),
            ],
            'mode' => ['sometimes', Rule::in([StatusPropagation::MODE_ALL, StatusPropagation::MODE_ANY])],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = Status::find($this->input('fromStatusId'));
            $to = Status::find($this->input('toStatusId'));

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
}
