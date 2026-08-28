<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Billing;

use App\Modules\Billing\Services\InvoiceClosure;
use App\Shared\Database\MorphMap;
use App\Shared\Http\Rules\ExistsInStatusReferential;
use App\Shared\Organizations\CurrentOrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification de l'en-tête d'une facture.
 *
 * `customerId` n'est pas modifiable : les lignes référencent des commandes de ce
 * client, changer de client les rendrait incohérentes.
 */
class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.not_in' => 'La clôture passe par « Clôturer la facture » : '
                .'elle fige le document et déclenche ses envois.',
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganizationContext::class)->getOrganizationId();
        $invoiceId = $this->route('invoice')?->id;

        return [
            'invoiceNumber' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('invoices', 'invoice_number')->where('organization_id', $organizationId)->ignore($invoiceId),
            ],
            'invoiceDate' => ['sometimes', 'date'],
            'periodFrom' => ['sometimes', 'nullable', 'date'],
            'periodTo' => ['sometimes', 'nullable', 'date', 'after_or_equal:periodFrom'],
            'currencyCode' => ['sometimes', 'string', 'size:3'],
            'externalReference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remark' => ['sometimes', 'nullable', 'string'],
            // Le referentiel gouverne les codes ; la cloture, elle, ne passe
            // pas par ici.
            'status' => [
                'sometimes', 'string', 'max:32',
                new ExistsInStatusReferential(MorphMap::INVOICE),
                Rule::notIn([InvoiceClosure::CLOSED]),
            ],
        ];
    }
}
