<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\ProviderSettlements;

use App\Shared\Organizations\CurrentOrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProviderSettlementRequest extends FormRequest
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
        $organizationId = app(CurrentOrganizationContext::class)->getOrganizationId();
        $settlementId = $this->route('providerSettlement')?->id;

        return [
            'settlementNumber' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('provider_settlements', 'settlement_number')
                    ->where('organization_id', $organizationId)
                    ->ignore($settlementId),
            ],
            'periodFrom' => ['sometimes', 'nullable', 'date'],
            'periodTo' => ['sometimes', 'nullable', 'date', 'after_or_equal:periodFrom'],
            'taxTotal' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', 'max:32'],
        ];
    }
}
