<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Stock;

use App\Shared\Database\MorphMap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un mouvement de stock.
 *
 * `sourceEntityType` n'accepte que des **alias de la morph map** : le §18
 * interdit de stocker un nom de classe PHP et demande de limiter les types
 * autorisés. La liste est dérivée de la morph map existante, jamais recopiée.
 *
 * `movementType` reste libre : le diagramme n'en énumère aucune valeur.
 *
 * Les règles source/destination — au moins l'une, différentes, même dépôt —
 * relèvent de l'Action : elles ont besoin des emplacements chargés.
 */
class StoreStockMovementRequest extends FormRequest
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
            'stockItemId' => ['required', 'ulid'],
            'sourceLocationId' => ['nullable', 'ulid'],
            'destinationLocationId' => ['nullable', 'ulid'],
            'movementType' => ['required', 'string', 'max:64'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'sourceEntityType' => ['nullable', 'string', Rule::in(array_keys(MorphMap::registered()))],
            'sourceEntityId' => ['nullable', 'ulid', 'required_with:sourceEntityType'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sourceEntityType.in' => 'Ce type d’entité source n’est pas reconnu.',
            'quantity.gt' => 'La quantité d’un mouvement doit être strictement positive.',
        ];
    }
}
