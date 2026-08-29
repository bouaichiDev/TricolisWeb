<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Pricing;

use App\Modules\Orders\Models\Service;
use App\Modules\Pricing\Services\ConditionMatcher;
use App\Modules\Pricing\Services\PricingContext;
use App\Shared\Http\Rules\BelongsToActiveOrganization;
use App\Shared\Http\Rules\ValidPricingFormula;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création ou modification d'une règle.
 *
 * **La formule est obligatoire et validée ici** (§169U) : une règle active sans
 * formule ne calcule rien, et la matrice ne la remplace pas. La valider à
 * l'enregistrement évite de découvrir la faute au moment de facturer.
 *
 * Les conditions sont envoyées avec la règle : elles n'existent pas sans elle,
 * et les éditer séparément obligerait à trois appels pour une seule pensée.
 */
class StorePriceRuleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $context = app(PricingContext::class);
        $priceListId = $this->route('priceList')?->id ?? $this->route('priceRule')?->price_list_id;
        $ruleId = $this->route('priceRule')?->id;

        return [
            'code' => [
                $this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:64',
                Rule::unique('price_rules', 'code')->where('price_list_id', $priceListId)->ignore($ruleId),
            ],
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:255'],
            'formula' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', new ValidPricingFormula],
            'serviceId' => ['sometimes', 'nullable', 'ulid', new BelongsToActiveOrganization(Service::class)],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'isActive' => ['sometimes', 'boolean'],

            'conditions' => ['sometimes', 'array', 'max:20'],
            'conditions.*.variable' => [
                'required',
                // Le catalogue de la plateforme fait foi : une condition ne
                // porte que sur une variable qu'un superadmin a declaree.
                Rule::in(array_merge($context->numericNames(), $context->dimensionNames())),
            ],
            'conditions.*.operator' => ['required', Rule::in(ConditionMatcher::OPERATORS)],
            'conditions.*.valueFrom' => ['required', 'string', 'max:255'],
            'conditions.*.valueTo' => ['nullable', 'string', 'max:255', 'required_if:conditions.*.operator,between'],
        ];
    }
}
