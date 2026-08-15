<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Claims;

use App\Http\Resources\Api\V1\Customers\CustomerCompactResource;
use App\Http\Resources\Api\V1\Identity\UserCompactResource;
use App\Http\Resources\Api\V1\Orders\OrderCompactResource;
use App\Http\Resources\Api\V1\Tours\TourCompactResource;
use App\Modules\Claims\Models\Claim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Détail d'une réclamation, champs d'instruction compris.
 *
 * @mixin Claim
 */
class ClaimDetailResource extends JsonResource
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
            'orderServiceId' => $this->order_service_id,
            'tourId' => $this->tour_id,
            'title' => $this->title,
            'description' => $this->description,
            'claimType' => $this->claim_type,
            'cause' => $this->cause,
            'decision' => $this->decision,
            'followUp' => $this->follow_up,
            'result' => $this->result,
            'cost' => $this->cost,
            'status' => $this->status,
            'createdBy' => $this->created_by,
            'responsibleUserId' => $this->responsible_user_id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'closedAt' => $this->closed_at?->toIso8601String(),
            'customer' => new CustomerCompactResource($this->whenLoaded('customer')),
            'order' => new OrderCompactResource($this->whenLoaded('order')),
            'tour' => new TourCompactResource($this->whenLoaded('tour')),
            'creator' => new UserCompactResource($this->whenLoaded('creator')),
            'responsibleUser' => new UserCompactResource($this->whenLoaded('responsibleUser')),
        ];
    }
}
