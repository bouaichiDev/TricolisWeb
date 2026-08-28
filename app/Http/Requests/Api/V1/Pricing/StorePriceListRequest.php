<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Pricing;

use App\Modules\Customers\Models\Customer;
use App\Modules\Pricing\Models\PriceList;
use App\Shared\Http\Rules\BelongsToActiveOrganization;
use App\Shared\Organizations\CurrentOrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un barème.
 *
 * Une liste `customer` sans client n'a personne à servir, et une liste
 * `global` avec des clients contredirait sa portée : les deux sont refusées
 * ici plutôt que découvertes à la première facture.
 */
class StorePriceListRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganizationContext::class)->getOrganizationId();

        return [
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('price_lists', 'code')->where('organization_id', $organizationId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'scope' => ['required', Rule::in([PriceList::GLOBAL, PriceList::CUSTOMER])],
            'validFrom' => ['nullable', 'date'],
            'validTo' => ['nullable', 'date', 'after_or_equal:validFrom'],
            'isActive' => ['sometimes', 'boolean'],

            'customerIds' => [
                Rule::requiredIf(fn (): bool => $this->input('scope') === PriceList::CUSTOMER),
                'array',
            ],
            'customerIds.*' => ['ulid', new BelongsToActiveOrganization(Customer::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customerIds.required' => 'Un tarif client doit désigner au moins un client.',
        ];
    }
}
