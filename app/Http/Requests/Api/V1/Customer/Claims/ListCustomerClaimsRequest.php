<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Claims;

use App\Modules\CustomerPortal\Queries\CustomerClaimListQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtres de la liste des réclamations (§7). Aucun `customerId` : le client
 * vient du jeton, et le paramètre est refusé s'il est envoyé.
 */
class ListCustomerClaimsRequest extends FormRequest
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
        return [
            'customerId' => ['prohibited'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'array', 'max:20'],
            'status.*' => ['string', 'max:32'],
            'claimType' => ['sometimes', 'nullable', 'string', 'max:64'],
            'orderId' => ['sometimes', 'nullable', 'string', 'ulid'],
            'orderServiceId' => ['sometimes', 'nullable', 'string', 'ulid'],
            'state' => ['sometimes', 'nullable', Rule::in(['open', 'closed'])],
            'createdFrom' => ['sometimes', 'nullable', 'date'],
            'createdTo' => ['sometimes', 'nullable', 'date', 'after_or_equal:createdFrom'],
            'closedFrom' => ['sometimes', 'nullable', 'date'],
            'closedTo' => ['sometimes', 'nullable', 'date', 'after_or_equal:closedFrom'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', Rule::in(array_keys(CustomerClaimListQuery::SORTS))],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
