<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Types;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La source d'une valeur ne change pas.
 *
 * Déplacer « Palette » des colis vers les véhicules laisserait chaque colis qui
 * s'y réfère avec un type qui n'existe plus dans sa liste.
 */
class UpdateTypeItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:64'],
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:32'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
