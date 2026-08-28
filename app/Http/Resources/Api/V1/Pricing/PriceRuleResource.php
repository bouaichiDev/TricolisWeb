<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Pricing;

use App\Modules\Pricing\Models\PriceRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une règle et sa formule.
 *
 * `matrixDriven` dit qu'une matrice la désigne : elle ne s'applique alors que
 * par ses zones, et l'écran doit le montrer plutôt que de laisser croire
 * qu'elle vaut partout.
 *
 * @mixin PriceRule
 */
class PriceRuleResource extends JsonResource
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
            'serviceName' => $this->whenLoaded('service', fn () => $this->service?->name),
            'code' => $this->code,
            'name' => $this->name,
            'formula' => $this->formula,
            'priority' => $this->priority,
            'isActive' => $this->is_active,
            'matrixDriven' => $this->whenCounted('matrixRows', fn (int $count): bool => $count > 0),
            'conditions' => PriceRuleConditionResource::collection($this->whenLoaded('conditions')),
        ];
    }
}
