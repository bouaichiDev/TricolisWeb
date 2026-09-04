<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Types;

use App\Shared\Http\Requests\ListRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Filtres propres aux valeurs de référentiel.
 *
 * `type` prend le **code** de la source — `vehicle`, `package` — plutôt que son
 * identifiant : un écran qui veut les types de véhicule connaît le code, pas
 * l'ULID que l'organisme a tiré.
 */
class ListTypeItemRequest extends ListRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'type' => ['sometimes', 'string', 'max:64'],
            'typeId' => ['sometimes', 'ulid'],
        ]);
    }
}
