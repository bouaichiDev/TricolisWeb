<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Modification de l'en-tête d'une commande.
 *
 * Ni le numéro ni le statut ne sont modifiables ici : le premier est attribué
 * par la séquence, le second par l'endpoint de transition.
 */
class UpdateOrderRequest extends FormRequest
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
            'depotId' => ['sometimes', 'nullable', 'ulid'],
            'externalReference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'customerReference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'orderType' => ['sometimes', 'nullable', 'string', 'max:64'],
            'groupCode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'orderDate' => ['sometimes', 'date'],
            'currencyCode' => ['sometimes', 'string', 'size:3', 'uppercase'],
            'internalRemark' => ['sometimes', 'nullable', 'string'],
            'workerRemark' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
