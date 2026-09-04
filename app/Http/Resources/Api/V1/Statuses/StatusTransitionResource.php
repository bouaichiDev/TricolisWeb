<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Statuses;

use App\Modules\Statuses\Models\StatusTransition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StatusTransition */
class StatusTransitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fromStatusId' => $this->from_status_id,
            'toStatusId' => $this->to_status_id,
            'isManual' => $this->is_manual,
            'to' => new StatusResource($this->whenLoaded('to')),
        ];
    }
}
