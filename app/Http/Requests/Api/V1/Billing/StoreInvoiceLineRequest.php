<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ajout d'une ligne à une facture existante.
 *
 * L'unicité de `orderServiceId` est portée par la base — un service n'est
 * facturé qu'une fois — et vérifiée ici pour renvoyer un 422 lisible plutôt
 * qu'une erreur SQL.
 */
class StoreInvoiceLineRequest extends FormRequest
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

        return [
            'orderServiceId' => ['nullable', 'ulid', Rule::unique('invoice_lines', 'order_service_id')],
            'orderId' => ['nullable', 'ulid'],
            'lineNumber' => [
                'required', 'integer', 'min:1',
                Rule::unique('invoice_lines', 'line_number')->where('invoice_id', $invoiceId),
            ],
            'serviceCode' => ['nullable', 'string', 'max:64'],
            'description' => ['required', 'string', 'max:255'],
            'customerOrderReference' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unitPrice' => ['required', 'numeric', 'min:0'],
            'discountRate' => ['sometimes', 'numeric', 'between:0,100'],
            'taxRate' => ['sometimes', 'numeric', 'between:0,100'],
            'serviceCompletedAt' => ['nullable', 'date'],
            'status' => ['required', 'string', 'max:32'],

            'addressSnapshot' => ['sometimes', 'array'],
            'addressSnapshot.addressCode' => ['nullable', 'string', 'max:64'],
            'addressSnapshot.name' => ['nullable', 'string', 'max:255'],
            'addressSnapshot.addressLine1' => ['nullable', 'string', 'max:255'],
            'addressSnapshot.addressLine2' => ['nullable', 'string', 'max:255'],
            'addressSnapshot.postalCode' => ['nullable', 'string', 'max:32'],
            'addressSnapshot.city' => ['nullable', 'string', 'max:255'],
            'addressSnapshot.country' => ['nullable', 'string', 'max:255'],
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
