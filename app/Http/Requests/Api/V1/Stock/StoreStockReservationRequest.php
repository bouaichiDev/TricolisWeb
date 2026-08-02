<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Stock;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'une réservation.
 *
 * `releasedAt` n'est pas accepté : une réservation naît active, la libération
 * passe par sa propre route.
 */
class StoreStockReservationRequest extends FormRequest
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
            'stockLocationId' => ['required', 'ulid'],
            'orderLineId' => ['required', 'ulid'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'status' => ['required', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.gt' => 'La quantité réservée doit être strictement positive.',
        ];
    }
}
