<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\ProviderSettlements;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProviderSettlementLineRequest extends FormRequest
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
            'orderServiceId' => ['nullable', 'ulid', Rule::unique('provider_settlement_lines', 'order_service_id')],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unitCost' => ['required', 'numeric', 'min:0'],
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
