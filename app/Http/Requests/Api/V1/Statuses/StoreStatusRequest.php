<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Statuses;

use App\Modules\Statuses\Services\StatusSources;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un statut du référentiel.
 *
 * `source` est validé contre la morph map filtrée sur les entités qui portent
 * réellement une colonne `status` : créer un statut pour une entité qui n'en a
 * pas produirait une ligne que rien ne pourrait jamais désigner.
 *
 * `code` et `status` sont uniques **par source** : « draft » existe pour une
 * commande comme pour un colis, et ce sont deux statuts distincts.
 */
class StoreStatusRequest extends FormRequest
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
            'source' => ['required', 'string', 'max:64', Rule::in(StatusSources::all())],
            'status' => [
                'required',
                'integer',
                'min:0',
                Rule::unique('statuses', 'status')->where('source', $this->input('source')),
            ],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('statuses', 'code')->where('source', $this->input('source')),
            ],
            'label' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],
            'active' => ['sometimes', 'boolean'],
            'isToSend' => ['sometimes', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
