<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Orders;

use App\Http\Resources\Api\V1\Addresses\AddressResource;
use App\Modules\Orders\Models\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Service d'une commande.
 *
 * Les champs financiers sont séparés en trois blocs : opérationnel, facturation
 * client et coût fournisseur. Aucun n'est calculé ici — ils sont restitués tels
 * qu'enregistrés, le moteur de tarification viendra plus tard.
 *
 * @mixin OrderService
 */
class OrderServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderId' => $this->order_id,
            'serviceId' => $this->service_id,
            'addressId' => $this->address_id,
            'serviceNumber' => $this->service_number,
            'sequence' => $this->sequence,
            'operational' => [
                'requestedDate' => $this->requested_date,
                'requestedFrom' => $this->requested_from,
                'requestedTo' => $this->requested_to,
                'quantity' => $this->quantity,
                'unit' => $this->unit,
                'requiredTimeMinutes' => $this->required_time_minutes,
                'remainingTimeMinutes' => $this->remaining_time_minutes,
                'weight' => $this->weight,
                'volume' => $this->volume,
                'packageCount' => $this->package_count,
                'instructions' => $this->instructions,
            ],
            'billing' => [
                'customerUnitPrice' => $this->customer_unit_price,
                'customerTotalPrice' => $this->customer_total_price,
            ],
            'providerCost' => [
                'providerUnitCost' => $this->provider_unit_cost,
                'providerTotalCost' => $this->provider_total_cost,
            ],
            'status' => $this->status?->value,
            'service' => new ServiceResource($this->whenLoaded('service')),
            'address' => new AddressResource($this->whenLoaded('address')),
            'contacts' => OrderServiceContactResource::collection($this->whenLoaded('contacts')),
            'packages' => $this->whenLoaded('servicePackages', fn () => $this->servicePackages->map(fn ($link): array => [
                'id' => $link->id,
                'packageId' => $link->package_id,
                'quantity' => $link->quantity,
                'handlingInstructions' => $link->handling_instructions,
                'status' => $link->status,
            ])),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
