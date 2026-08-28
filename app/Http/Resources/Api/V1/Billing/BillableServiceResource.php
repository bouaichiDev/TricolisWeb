<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Billing;

use App\Modules\Orders\Models\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une prestation proposée à la facturation.
 *
 * Le §44 énumère ce qu'un facturier a besoin de voir pour décider : la commande,
 * la référence du client, le service, sa date, sa quantité, son prix et son
 * adresse. Un identifiant ne suffit pas à reconnaître une prestation.
 *
 * `alreadyInvoiced` est toujours faux : la requête ne rend que ce qui reste à
 * facturer. Le champ existe parce que le §44 le nomme, et parce qu'il rend
 * l'invariant lisible dans la réponse.
 *
 * @mixin OrderService
 */
class BillableServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serviceNumber' => $this->service_number,
            'orderId' => $this->order_id,
            'orderNumber' => $this->whenLoaded('order', fn () => $this->order->order_number),
            'customerReference' => $this->whenLoaded('order', fn () => $this->order->customer_reference),
            'serviceCode' => $this->whenLoaded('service', fn () => $this->service?->code),
            'serviceName' => $this->whenLoaded('service', fn () => $this->service?->name),
            'requestedDate' => $this->requested_date?->toDateString(),
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'customerUnitPrice' => (float) $this->customer_unit_price,
            'customerTotalPrice' => (float) $this->customer_total_price,
            'weight' => (float) $this->weight,
            'volume' => (float) $this->volume,
            'packageCount' => (int) $this->package_count,
            'status' => $this->status?->value ?? $this->status,
            'alreadyInvoiced' => false,
            'address' => $this->whenLoaded('address', fn (): ?array => $this->address === null ? null : [
                'id' => $this->address->id,
                'code' => $this->address->code,
                'name' => $this->address->name,
                'addressLine1' => $this->address->address_line_1,
                'addressLine2' => $this->address->address_line_2,
                'postalCode' => $this->address->postal_code,
                'city' => $this->address->city,
                'country' => $this->address->country,
            ]),
        ];
    }
}
