<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Organizations;

use App\Modules\Organizations\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subscription */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'planCode' => $this->plan_code,
            'status' => $this->status?->value,
            'statusLabel' => $this->status?->label(),
            'startsAt' => $this->starts_at,
            'endsAt' => $this->ends_at,
            'trialEndsAt' => $this->trial_ends_at,
            'onTrial' => $this->onTrial(),
            'hasEnded' => $this->hasEnded(),
            'grantsAccess' => $this->grantsAccess(),
        ];
    }
}
