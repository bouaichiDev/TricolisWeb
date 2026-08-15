<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Billing;

use App\Shared\Organizations\CurrentOrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une facture, lignes comprises.
 *
 * `lines` est **obligatoire et non vide** : `Invoice "1" *-- "1..*" InvoiceLine`
 * interdit une facture sans ligne.
 *
 * Les six montants calculés — `subtotal`, `taxTotal`, `total`,
 * `totalExcludingTax`, `totalIncludingTax` — ne sont **pas** acceptés. Le §11
 * l'exige : « ne jamais faire confiance aux totaux envoyés ». Les fournir n'a
 * aucun effet.
 */
class StoreInvoiceRequest extends FormRequest
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
        $organizationId = app(CurrentOrganizationContext::class)->getOrganizationId();

        return [
            'customerId' => ['required', 'ulid'],
            'invoiceNumber' => [
                'required', 'string', 'max:255',
                Rule::unique('invoices', 'invoice_number')->where('organization_id', $organizationId),
            ],
            'invoiceDate' => ['required', 'date'],
            'periodFrom' => ['nullable', 'date'],
            'periodTo' => ['nullable', 'date', 'after_or_equal:periodFrom'],
            'currencyCode' => ['required', 'string', 'size:3'],
            'externalReference' => ['nullable', 'string', 'max:255'],
            'remark' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:32'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.orderServiceId' => ['nullable', 'ulid', 'distinct'],
            'lines.*.orderId' => ['nullable', 'ulid'],
            'lines.*.lineNumber' => ['required', 'integer', 'min:1', 'distinct'],
            'lines.*.serviceCode' => ['nullable', 'string', 'max:64'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.customerOrderReference' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.unitPrice' => ['required', 'numeric', 'min:0'],
            'lines.*.discountRate' => ['sometimes', 'numeric', 'between:0,100'],
            'lines.*.taxRate' => ['sometimes', 'numeric', 'between:0,100'],
            'lines.*.serviceCompletedAt' => ['nullable', 'date'],
            'lines.*.status' => ['required', 'string', 'max:32'],

            'lines.*.addressSnapshot' => ['sometimes', 'array'],
            'lines.*.addressSnapshot.addressCode' => ['nullable', 'string', 'max:64'],
            'lines.*.addressSnapshot.name' => ['nullable', 'string', 'max:255'],
            'lines.*.addressSnapshot.addressLine1' => ['nullable', 'string', 'max:255'],
            'lines.*.addressSnapshot.addressLine2' => ['nullable', 'string', 'max:255'],
            'lines.*.addressSnapshot.postalCode' => ['nullable', 'string', 'max:32'],
            'lines.*.addressSnapshot.city' => ['nullable', 'string', 'max:255'],
            'lines.*.addressSnapshot.country' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Une facture doit porter au moins une ligne.',
            'lines.min' => 'Une facture doit porter au moins une ligne.',
        ];
    }
}
