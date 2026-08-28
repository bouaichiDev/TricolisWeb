<?php

declare(strict_types=1);

namespace App\Modules\Pricing\DTOs;

use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceMatrix;
use App\Modules\Pricing\Models\PriceMatrixRow;
use App\Modules\Pricing\Models\PriceRule;

/**
 * La règle retenue, et par quel chemin.
 *
 * Le chemin compte autant que la règle : le §169BH veut qu'on puisse expliquer
 * « 87.50 » par la liste, la matrice, la zone et la formule qui l'ont produit.
 * Sans cela, un tarif surprenant se discute sans arguments.
 */
final readonly class ResolvedPricing
{
    public function __construct(
        public PriceList $priceList,
        public PriceRule $rule,
        public ?PriceMatrix $matrix = null,
        public ?PriceMatrixRow $row = null,
    ) {}

    /** `customer` ou `global` : d'où vient le tarif appliqué. */
    public function scope(): string
    {
        return $this->priceList->scope;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scope' => $this->scope(),
            'priceListId' => $this->priceList->id,
            'priceListName' => $this->priceList->name,
            'priceRuleId' => $this->rule->id,
            'priceRuleCode' => $this->rule->code,
            'priceRuleName' => $this->rule->name,
            'formula' => $this->rule->formula,
            'priceMatrixId' => $this->matrix?->id,
            'priceMatrixName' => $this->matrix?->name,
            'priceMatrixRowId' => $this->row?->id,
            'zone' => $this->row?->label,
        ];
    }
}
