<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\ProviderSettlements;

use App\Shared\Organizations\CurrentOrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un décompte fournisseur, lignes comprises.
 *
 * `taxTotal` est accepté — il est saisi, aucune règle fiscale n'étant définie —
 * mais `subtotal` et `total` ne le sont pas : ils sont dérivés des lignes.
 */
class StoreProviderSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sur la route imbriquée `/providers/{provider}/settlements`, le
     * fournisseur vient de l'URL.
     */
    protected function prepareForValidation(): void
    {
        $provider = $this->route('provider');

        if ($provider !== null) {
            $this->merge(['providerId' => $provider->id]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganizationContext::class)->getOrganizationId();

        return [
            'providerId' => ['required', 'ulid'],
            'settlementNumber' => [
                'required', 'string', 'max:255',
                Rule::unique('provider_settlements', 'settlement_number')->where('organization_id', $organizationId),
            ],
            'periodFrom' => ['nullable', 'date'],
            'periodTo' => ['nullable', 'date', 'after_or_equal:periodFrom'],
            'taxTotal' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'max:32'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.orderServiceId' => ['nullable', 'ulid', 'distinct'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.unitCost' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Un décompte doit porter au moins une ligne.',
            'lines.min' => 'Un décompte doit porter au moins une ligne.',
        ];
    }
}
