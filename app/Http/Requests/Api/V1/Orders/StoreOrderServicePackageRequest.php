<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rattache un colis à un service de commande.
 *
 * Le colis doit appartenir à la même commande : le contrôleur le vérifie, la
 * validation ne le peut pas — elle ne connaît pas la commande.
 */
class StoreOrderServicePackageRequest extends FormRequest
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
            'packageId' => ['required', 'ulid'],
            'quantity' => ['sometimes', 'numeric', 'gt:0'],
            'handlingInstructions' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'max:32'],
        ];
    }
}
