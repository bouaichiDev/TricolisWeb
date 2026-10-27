<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Orders;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Le motif est facultatif ici : c'est le workflow (`requires_reason` du
 * statut cible) qui décide s'il est obligatoire, et `ChangeOrderStatus` qui
 * le fait respecter.
 */
class CancelCustomerOrderRequest extends FormRequest
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
        return ['reason' => ['nullable', 'string', 'max:1000']];
    }
}
