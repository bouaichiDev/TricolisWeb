<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Pricing;

use App\Modules\Pricing\Models\PriceList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un barème, tel que l'écran le lit.
 *
 * @mixin PriceList
 */
class PriceListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'code' => $this->code,
            'name' => $this->name,
            'scope' => $this->scope,
            'validFrom' => $this->valid_from?->toDateString(),
            'validTo' => $this->valid_to?->toDateString(),
            'isActive' => $this->is_active,
            'ruleCount' => $this->whenCounted('rules'),
            'matrixCount' => $this->whenCounted('matrices'),
            'customers' => $this->whenLoaded('customers', fn () => $this->customers
                ->map(fn ($customer): array => [
                    'id' => $customer->id,
                    'code' => $customer->code,
                    'name' => $customer->name,
                ])->values()),
            'rules' => PriceRuleResource::collection($this->whenLoaded('rules')),
            'matrices' => PriceMatrixResource::collection($this->whenLoaded('matrices')),
        ];
    }
}
