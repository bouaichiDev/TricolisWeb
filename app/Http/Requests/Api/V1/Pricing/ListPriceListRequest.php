<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Pricing;

use App\Modules\Pricing\Models\PriceList;
use App\Shared\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

/**
 * Filtres de la liste des barèmes.
 *
 * `scope` sépare le barème général des barèmes négociés : on ne les consulte
 * pas au même moment, et les mélanger ferait chercher longtemps.
 */
class ListPriceListRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'scope' => ['sometimes', Rule::in([PriceList::GLOBAL, PriceList::CUSTOMER])],
            'customerId' => ['sometimes', 'ulid'],
        ]);
    }
}
