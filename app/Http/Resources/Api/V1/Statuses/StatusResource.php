<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Statuses;

use App\Modules\Statuses\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Status */
class StatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'status' => $this->status,
            'code' => $this->code,
            'label' => $this->label,
            'icon' => $this->icon,
            'active' => $this->active,
            'isToSend' => $this->is_to_send,
            'position' => $this->position,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
