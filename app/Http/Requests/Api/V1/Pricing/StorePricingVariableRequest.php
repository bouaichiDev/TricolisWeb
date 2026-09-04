<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Pricing;

use App\Modules\Pricing\Services\PricingVariableSources;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création ou modification d'une variable tarifaire.
 *
 * **Le code est le nom écrit dans les formules.** Il suit donc la même
 * grammaire que le tokenizer accepte — lettres, chiffres, tirets bas, une
 * lettre en tête — sinon `{P:mon code}` ne serait jamais reconnu, et la
 * variable resterait inutilisable sans qu'on sache pourquoi.
 *
 * **La source vient d'une liste fermée.** Le §67 refuse un chemin arbitraire
 * vers le modèle : c'est le code qui sait aller de la prestation à la valeur,
 * et une table saisie à la main n'aurait ni chemin ni garantie.
 */
class StorePricingVariableRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $variableId = $this->route('pricingVariable')?->id;
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'code' => [
                $required, 'string', 'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('pricing_variables', 'code')->ignore($variableId),
            ],
            'label' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'sourceKey' => [$required, Rule::in(PricingVariableSources::keys())],
            'unit' => ['sometimes', 'nullable', 'string', 'max:32'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Le code ne peut contenir que des lettres minuscules, '
                .'des chiffres et des tirets bas, et doit commencer par une lettre.',
            'sourceKey.in' => 'Cette source n’existe pas : seules celles du registre sont lisibles.',
        ];
    }
}
