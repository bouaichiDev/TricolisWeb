<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Orders;

use App\Modules\Orders\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Service */
class ServiceResource extends JsonResource
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
            'unit' => $this->unit,
            'defaultDurationMinutes' => $this->default_duration_minutes,
            'billableToCustomer' => $this->billable_to_customer,
            'payableToProvider' => $this->payable_to_provider,
            'requiresAddress' => $this->requires_address,
            'requiresContact' => $this->requires_contact,
            'status' => $this->status,
        ];
    }
}
