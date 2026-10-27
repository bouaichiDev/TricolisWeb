<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Claims;

use App\Modules\Claims\Models\Claim;
use App\Modules\CustomerPortal\Services\CustomerStatusLabels;
use App\Shared\Database\MorphMap;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une réclamation dans la liste du portail (§8).
 *
 * Construite champ par champ, jamais depuis `ClaimListResource` : celle-ci
 * porte le coût, le résultat et le responsable interne. Rien de cela ne sort
 * vers le client — aucune règle métier ne prévoit qu'il les lise.
 *
 * @mixin Claim
 */
class CustomerClaimListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->order;
        $service = $this->orderService;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'claimType' => $this->claim_type,
            'status' => app(CustomerStatusLabels::class)->present(MorphMap::CLAIM, $this->status),
            'isClosed' => $this->isClosed(),
            'order' => $order === null ? null : [
                'id' => $order->id,
                'reference' => $order->order_number,
                'customerReference' => $order->customer_reference,
            ],
            'orderService' => $service === null ? null : [
                'id' => $service->id,
                'serviceNumber' => $service->service_number,
                'name' => $service->service?->name,
            ],
            'createdAt' => $this->created_at?->toIso8601String(),
            'closedAt' => $this->closed_at?->toIso8601String(),
        ];
    }
}
