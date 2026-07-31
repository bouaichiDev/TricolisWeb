<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customers;

use App\Modules\Customers\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
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
            'legalName' => $this->legal_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'paymentMode' => $this->payment_mode,
            'communicationMode' => $this->communication_mode,
            'catalogEnabled' => $this->catalog_enabled,
            'stockEnabled' => $this->stock_enabled,
            'packageEnabled' => $this->package_enabled,
            'appointmentEnabled' => $this->appointment_enabled,
            'trackingEnabled' => $this->tracking_enabled,
            'status' => $this->status?->value,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
