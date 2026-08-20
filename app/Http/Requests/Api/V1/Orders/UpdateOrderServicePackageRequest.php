<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Modifie une prise en charge de colis.
 *
 * Le colis lui-même n'est pas modifiable : changer `packageId` reviendrait à
 * remplacer la liaison. On la retire et on en crée une autre.
 */
class UpdateOrderServicePackageRequest extends FormRequest
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
            'quantity' => ['sometimes', 'numeric', 'gt:0'],
            'handlingInstructions' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', 'max:32'],
        ];
    }
}
