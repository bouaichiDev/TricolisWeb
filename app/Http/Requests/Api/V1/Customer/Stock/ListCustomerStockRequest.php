<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Stock;

use App\Modules\CustomerPortal\Queries\Stock\CustomerStockItemsQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtres des listes du stock (§10, §16, §17) : articles, mouvements,
 * réservations. Aucun `customerId` : le client vient du jeton, et le
 * paramètre est refusé s'il est envoyé.
 */
class ListCustomerStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ulid = ['sometimes', 'nullable', 'string', 'ulid'];

        return [
            'customerId' => ['prohibited'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'articleCode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:128'],
            'status' => ['sometimes', 'nullable'],
            'status.*' => ['string', 'max:32'],
            'movementType' => ['sometimes', 'nullable', 'string', 'max:64'],
            'sourceEntityType' => ['sometimes', 'nullable', 'string', 'max:64'],
            'state' => ['sometimes', 'nullable', Rule::in(['active', 'released'])],
            ...array_fill_keys(['catalogItemId', 'depotId', 'locationId', 'stockItemId', 'sourceLocationId', 'destinationLocationId', 'sourceEntityId', 'orderId'], $ulid),
            'createdFrom' => ['sometimes', 'nullable', 'date'],
            'createdTo' => ['sometimes', 'nullable', 'date', 'after_or_equal:createdFrom'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', Rule::in(array_keys(CustomerStockItemsQuery::SORTS))],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
