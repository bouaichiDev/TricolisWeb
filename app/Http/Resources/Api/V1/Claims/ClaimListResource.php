<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Claims;

use App\Modules\Claims\Models\Claim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réclamation vue depuis une liste.
 *
 * Ni `claimNumber`, ni `severity` : absents du diagramme.
 *
 * @mixin Claim
 */
class ClaimListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'customerId' => $this->customer_id,
            'orderId' => $this->order_id,
            'tourId' => $this->tour_id,
            'title' => $this->title,
            'claimType' => $this->claim_type,
            'result' => $this->result,
            'cost' => $this->cost,
            'status' => $this->status,
            'responsibleUserId' => $this->responsible_user_id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'closedAt' => $this->closed_at?->toIso8601String(),
            'customerName' => $this->whenLoaded('customer', fn () => $this->customer->name),
        ];
    }
}
