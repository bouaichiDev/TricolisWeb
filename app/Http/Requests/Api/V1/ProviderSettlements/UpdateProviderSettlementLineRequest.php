<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\ProviderSettlements;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProviderSettlementLineRequest extends FormRequest
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
        $lineId = $this->route('line')?->id;

        return [
            'orderServiceId' => [
                'sometimes', 'nullable', 'ulid',
                Rule::unique('provider_settlement_lines', 'order_service_id')->ignore($lineId),
            ],
            'description' => ['sometimes', 'string', 'max:255'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'unitCost' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'orderServiceId.unique' => 'Ce service est déjà décompté sur une autre ligne.',
        ];
    }
}
