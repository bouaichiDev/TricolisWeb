<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Statuses;

use App\Modules\Statuses\Models\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Remplace en une fois les transitions qui partent d'un statut.
 *
 * Un envoi complet plutôt qu'un ajout par arête : dessiner un cycle de vie se
 * fait d'un bloc, et deux requêtes concurrentes qui ajoutent puis retirent
 * laisseraient un graphe que personne n'a voulu.
 */
class SyncStatusTransitionsRequest extends FormRequest
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
            'transitions' => ['present', 'array'],
            'transitions.*.toStatusId' => ['required', 'ulid', 'distinct', Rule::exists('statuses', 'id')],
            'transitions.*.isManual' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Deux règles que la validation de champ ne peut pas exprimer : une
     * transition reste dans une entité, et un statut ne mène pas à lui-même.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Status $status */
            $status = $this->route('status');

            foreach ($this->input('transitions', []) as $index => $transition) {
                $target = Status::find($transition['toStatusId'] ?? null);

                if ($target === null) {
                    continue;
                }

                if ($target->id === $status->id) {
                    $validator->errors()->add(
                        "transitions.{$index}.toStatusId",
                        'Un statut ne peut pas mener à lui-même.',
                    );
                }

                if ($target->source !== $status->source) {
                    $validator->errors()->add(
                        "transitions.{$index}.toStatusId",
                        'Une transition relie deux statuts de la même entité.',
                    );
                }
            }
        });
    }
}
