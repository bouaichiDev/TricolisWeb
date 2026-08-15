<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceLineRequest extends FormRequest
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
        $invoiceId = $this->route('invoice')?->id;
        $lineId = $this->route('line')?->id;

        return [
            'orderServiceId' => [
                'sometimes', 'nullable', 'ulid',
                Rule::unique('invoice_lines', 'order_service_id')->ignore($lineId),
            ],
            'orderId' => ['sometimes', 'nullable', 'ulid'],
            'lineNumber' => [
                'sometimes', 'integer', 'min:1',
                Rule::unique('invoice_lines', 'line_number')->where('invoice_id', $invoiceId)->ignore($lineId),
            ],
            'serviceCode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'description' => ['sometimes', 'string', 'max:255'],
            'customerOrderReference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'unitPrice' => ['sometimes', 'numeric', 'min:0'],
            'discountRate' => ['sometimes', 'numeric', 'between:0,100'],
            'taxRate' => ['sometimes', 'numeric', 'between:0,100'],
            'serviceCompletedAt' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'orderServiceId.unique' => 'Ce service est déjà facturé sur une autre ligne.',
        ];
    }
}
