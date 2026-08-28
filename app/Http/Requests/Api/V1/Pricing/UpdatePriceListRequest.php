<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Pricing;

use App\Modules\Customers\Models\Customer;
use App\Shared\Http\Rules\BelongsToActiveOrganization;
use App\Shared\Organizations\CurrentOrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'un barème.
 *
 * La portée n'est pas modifiable : basculer une liste client en globale
 * l'appliquerait d'un coup à toute la clientèle, sans que personne ne l'ait
 * demandé.
 */
class UpdatePriceListRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganizationContext::class)->getOrganizationId();
        $priceListId = $this->route('priceList')?->id;

        return [
            'code' => [
                'sometimes', 'string', 'max:64',
                Rule::unique('price_lists', 'code')
                    ->where('organization_id', $organizationId)
                    ->ignore($priceListId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'validFrom' => ['sometimes', 'nullable', 'date'],
            'validTo' => ['sometimes', 'nullable', 'date', 'after_or_equal:validFrom'],
            'isActive' => ['sometimes', 'boolean'],

            'customerIds' => ['sometimes', 'array'],
            'customerIds.*' => ['ulid', new BelongsToActiveOrganization(Customer::class)],
        ];
    }
}
