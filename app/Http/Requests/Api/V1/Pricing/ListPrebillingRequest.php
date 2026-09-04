<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Pricing;

use App\Shared\Http\Requests\ListRequest;

/**
 * Filtres de la préfacturation.
 *
 * La période porte sur la date demandée du service, comme le sélecteur de
 * facturation : c'est elle qui dit quand la prestation devait avoir lieu.
 */
class ListPrebillingRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'customerId' => ['sometimes', 'ulid'],
            'periodFrom' => ['sometimes', 'date'],
            'periodTo' => ['sometimes', 'date', 'after_or_equal:periodFrom'],
        ]);
    }
}
