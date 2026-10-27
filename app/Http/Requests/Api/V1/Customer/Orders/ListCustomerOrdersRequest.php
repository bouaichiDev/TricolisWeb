<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Orders;

use App\Http\Requests\Api\V1\Customer\ServerControlledFields;
use App\Modules\CustomerPortal\Queries\CustomerOrderListQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCustomerOrdersRequest extends FormRequest
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
            ...ServerControlledFields::prohibit(ServerControlledFields::TENANT),
            'search' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'customerReference' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'array'],
            'status.*' => ['string', 'max:64'],
            'service' => ['nullable', 'string', 'ulid'],
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date', 'after_or_equal:dateFrom'],
            'sort' => ['nullable', Rule::in(array_keys(CustomerOrderListQuery::SORTS))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
