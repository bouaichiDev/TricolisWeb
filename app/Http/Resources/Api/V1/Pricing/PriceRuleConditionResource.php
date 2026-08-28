<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Pricing;

use App\Modules\Pricing\Models\PriceRuleCondition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PriceRuleCondition
 */
class PriceRuleConditionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'priceRuleId' => $this->price_rule_id,
            'variable' => $this->variable,
            'operator' => $this->operator,
            'valueFrom' => $this->value_from,
            'valueTo' => $this->value_to,
        ];
    }
}
