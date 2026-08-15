<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Billing;

use App\Shared\Http\Requests\ListRequest;

/**
 * Filtres propres aux factures (§8).
 *
 * `legacyId` n'y figure pas : la colonne n'existe pas — voir
 * `phase-6-analysis.md` §1.
 */
class ListInvoiceRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'customerId' => ['sometimes', 'ulid'],
            'invoiceNumber' => ['sometimes', 'string', 'max:255'],
            'invoiceDateFrom' => ['sometimes', 'date'],
            'invoiceDateTo' => ['sometimes', 'date', 'after_or_equal:invoiceDateFrom'],
            'periodFrom' => ['sometimes', 'date'],
            'periodTo' => ['sometimes', 'date'],
            'currencyCode' => ['sometimes', 'string', 'size:3'],
            'externalReference' => ['sometimes', 'string', 'max:255'],
        ]);
    }
}
