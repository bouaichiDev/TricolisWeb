<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Stock;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Modification d'une réservation, limitée au statut (§24).
 *
 * `quantity` et les trois clés étrangères ne sont pas acceptées : les changer
 * devrait ajuster le solde sous verrou. Pour réserver autrement, on libère et
 * on recrée.
 */
class UpdateStockReservationRequest extends FormRequest
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
