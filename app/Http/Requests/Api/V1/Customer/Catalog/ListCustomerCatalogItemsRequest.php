<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Catalog;

use App\Http\Requests\Api\V1\Customer\ServerControlledFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtres de la liste des articles : recherche, catalogue, statut, tri.
 * Le client est toujours celui de la session — jamais un paramètre.
 */
class ListCustomerCatalogItemsRequest extends FormRequest
{
    /** Tri accepté → colonne. */
    public const array SORTS = ['articleCode' => 'article_code', 'name' => 'name', 'createdAt' => 'created_at'];

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
            ...ServerControlledFields::prohibit(ServerControlledFields::TENANT),
            'search' => ['nullable', 'string', 'max:100'],
            'catalogId' => ['nullable', 'string', 'ulid'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
