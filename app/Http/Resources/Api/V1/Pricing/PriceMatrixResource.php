<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Pricing;

use App\Modules\Pricing\Models\PriceMatrix;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PriceMatrix
 */
class PriceMatrixResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'priceListId' => $this->price_list_id,
            'serviceId' => $this->service_id,
            'serviceCode' => $this->whenLoaded('service', fn () => $this->service?->code),
            'code' => $this->code,
            'name' => $this->name,
            'dimension' => $this->dimension,
            'isActive' => $this->is_active,
            'rows' => $this->whenLoaded('rows', fn () => $this->rows->map(fn ($row): array => [
                'id' => $row->id,
                'priceRuleId' => $row->price_rule_id,
                'priceRuleCode' => $row->priceRule?->code,
                'formula' => $row->priceRule?->formula,
                'label' => $row->label,
                'matchMode' => $row->match_mode,
                'rangeFrom' => $row->range_from,
                'rangeTo' => $row->range_to,
                'priority' => $row->priority,
            ])->values()),
        ];
    }
}
