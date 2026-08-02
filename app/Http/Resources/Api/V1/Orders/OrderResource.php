<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Orders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'organizationId' => $this->organization_id, 'customerId' => $this->customer_id, 'agencyId' => $this->agency_id, 'depotId' => $this->depot_id, 'orderNumber' => $this->order_number, 'externalReference' => $this->external_reference, 'customerReference' => $this->customer_reference, 'orderType' => $this->order_type, 'orderDate' => $this->order_date, 'source' => $this->source?->value, 'currencyCode' => $this->currency_code, 'status' => $this->status?->value, 'weight' => $this->weight, 'volume' => $this->volume, 'packageCount' => $this->package_count, 'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => ['id' => $line->id, 'name' => $line->name, 'articleCode' => $line->article_code, 'quantity' => $line->quantity, 'weight' => $line->weight, 'volume' => $line->volume, 'sellingPrice' => $line->selling_price])), 'services' => $this->whenLoaded('orderServices', fn () => $this->orderServices->map(fn ($service) => ['id' => $service->id, 'serviceId' => $service->service_id, 'addressId' => $service->address_id, 'serviceNumber' => $service->service_number, 'sequence' => $service->sequence, 'requestedDate' => $service->requested_date, 'requestedFrom' => $service->requested_from, 'requestedTo' => $service->requested_to, 'quantity' => $service->quantity, 'unit' => $service->unit, 'requiredTimeMinutes' => $service->required_time_minutes, 'remainingTimeMinutes' => $service->remaining_time_minutes, 'weight' => $service->weight, 'volume' => $service->volume, 'packageCount' => $service->package_count, 'customerUnitPrice' => $service->customer_unit_price, 'customerTotalPrice' => $service->customer_total_price, 'providerUnitCost' => $service->provider_unit_cost, 'providerTotalCost' => $service->provider_total_cost, 'instructions' => $service->instructions, 'status' => $service->status?->value, 'createdAt' => $service->created_at, 'updatedAt' => $service->updated_at])), 'createdAt' => $this->created_at, 'updatedAt' => $this->updated_at];
    }
}
