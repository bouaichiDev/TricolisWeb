<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Stock;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Libération d'une réservation.
 *
 * Le statut est fourni par l'appelant : le §23 demande de le « modifier selon
 * une valeur fournie et validée sans inventer d'enum ».
 */
class ReleaseStockReservationRequest extends FormRequest
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
            'status' => ['required', 'string', 'max:32'],
        ];
    }
}
