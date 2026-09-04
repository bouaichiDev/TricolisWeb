<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Pricing;

use App\Modules\Orders\Models\Service;
use App\Modules\Pricing\Models\PriceMatrix;
use App\Modules\Pricing\Models\PriceMatrixRow;
use App\Shared\Http\Rules\BelongsToActiveOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création ou modification d'une matrice, avec ses zones.
 *
 * Les zones partent avec la matrice : une matrice sans zone ne décide rien, et
 * les éditer séparément ferait exister un barème à moitié posé.
 *
 * Chaque zone désigne une règle — le §169W le veut ainsi : la matrice choisit
 * la formule, elle ne la porte pas.
 */
class StorePriceMatrixRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $priceListId = $this->route('priceList')?->id ?? $this->route('priceMatrix')?->price_list_id;
        $matrixId = $this->route('priceMatrix')?->id;
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'code' => [
                $required, 'string', 'max:64',
                Rule::unique('price_matrices', 'code')->where('price_list_id', $priceListId)->ignore($matrixId),
            ],
            'name' => [$required, 'string', 'max:255'],
            'dimension' => ['sometimes', Rule::in([PriceMatrix::POSTAL_CODE])],
            'serviceId' => ['sometimes', 'nullable', 'ulid', new BelongsToActiveOrganization(Service::class)],
            'isActive' => ['sometimes', 'boolean'],

            'rows' => [$required, 'array', 'min:1', 'max:200'],
            'rows.*.label' => ['required', 'string', 'max:255'],
            'rows.*.priceRuleId' => [
                'required', 'ulid',
                // La regle doit appartenir au meme bareme : designer celle d'un
                // autre ferait appliquer un tarif que ce bareme ne porte pas.
                Rule::exists('price_rules', 'id')->where('price_list_id', $priceListId),
            ],
            'rows.*.matchMode' => [
                'sometimes',
                Rule::in([PriceMatrixRow::NUMERIC, PriceMatrixRow::PREFIX, PriceMatrixRow::EXACT]),
            ],
            'rows.*.rangeFrom' => ['required', 'string', 'max:32'],
            'rows.*.rangeTo' => ['nullable', 'string', 'max:32'],
            'rows.*.priority' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.*.priceRuleId.exists' => 'Cette zone désigne une règle qui n’appartient pas au barème.',
            'rows.required' => 'Une matrice sans zone ne décide de rien.',
        ];
    }
}
