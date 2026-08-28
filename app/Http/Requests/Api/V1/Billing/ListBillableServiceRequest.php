<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Billing;

use App\Shared\Http\Requests\ListRequest;

/**
 * Filtres du sélecteur de prestations facturables.
 *
 * La période porte sur la **date demandée** du service : c'est elle qui dit
 * quand la prestation devait avoir lieu, et une facture couvre une période.
 */
class ListBillableServiceRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'periodFrom' => ['sometimes', 'date'],
            'periodTo' => ['sometimes', 'date', 'after_or_equal:periodFrom'],
        ]);
    }
}
