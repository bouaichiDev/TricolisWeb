<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Integrations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ce qu'un import demande, en plus du fichier.
 *
 * Le client vient de la configuration : elle lui appartient, et le redemander
 * ouvrirait la porte à importer chez un autre.
 *
 * L'agence, en revanche, est **obligatoire** : `orders.agency_id` est
 * `NOT NULL`, une commande sans agence n'existe pas. Le fichier d'un client ne
 * la porte pas — il ne connaît pas notre organisation — donc elle se choisit à
 * l'import. Le dépôt reste facultatif, comme en base : une commande peut
 * attendre son affectation.
 */
final class ImportOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120'],
            'agencyId' => ['required', 'ulid'],
            'depotId' => ['nullable', 'ulid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agencyId.required' => 'Choisissez l’agence qui prendra ces commandes en charge.',
            'file.max' => 'Un fichier d’import ne dépasse pas 5 Mo.',
        ];
    }
}
