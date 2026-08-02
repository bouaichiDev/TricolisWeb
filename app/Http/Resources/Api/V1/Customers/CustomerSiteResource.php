<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customers;

use App\Modules\Customers\Models\CustomerSite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerSite */
class CustomerSiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customer_id,
            'addressId' => $this->address_id,
            'code' => $this->code,
            'name' => $this->name,
            'siteType' => $this->site_type,
            'isDefault' => $this->is_default,
            'status' => $this->status,
        ];
    }
}
