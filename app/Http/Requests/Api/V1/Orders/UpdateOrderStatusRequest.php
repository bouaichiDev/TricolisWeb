<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use App\Modules\Orders\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    /**
     * Emplacements choisis, indexes par ligne de commande.
     *
     * @return array<string, string>
     */
    public function stockLocations(): array
    {
        $choices = $this->validated('stockLocations') ?? [];

        return array_column($choices, 'stockLocationId', 'orderLineId');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'reasonCode' => ['nullable', 'string', 'max:64'],
            'reasonText' => ['nullable', 'string', 'max:1000'],
            // Emplacement a vider pour une ligne stockee dans plusieurs
            // endroits. Inutile quand il n'y en a qu'un : le serveur le trouve.
            'stockLocations' => ['sometimes', 'array'],
            'stockLocations.*.orderLineId' => ['required', 'ulid'],
            'stockLocations.*.stockLocationId' => ['required', 'ulid'],
        ];
    }
}
