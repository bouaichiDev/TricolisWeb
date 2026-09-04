<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\ProviderSettlements;

use App\Modules\Orders\Models\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une prestation à régler à un fournisseur.
 *
 * **Le prix client est rendu, mais il n'est pas un coût.** Le §103 interdit de
 * s'en servir comme coût fournisseur : il est montré à titre indicatif, pour
 * qu'on voie ce que la prestation a rapporté face à ce qu'on s'apprête à payer.
 * Le coût unitaire, lui, se saisit — aucun champ du schéma ne le porte, et le
 * §169 interdit un moteur tarifaire qui l'inventerait.
 *
 * Le client servi est nommé : un fournisseur règle des prestations pour
 * plusieurs clients, et la ligne doit se reconnaître.
 *
 * @mixin OrderService
 */
class SettleableServiceResource extends JsonResource
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
            // Ce qu'on doit au fournisseur, tel que la commande l'a fixe. Le
            // prix client est a cote, a titre de reperage : il ne dit pas le
            // cout, et confondre les deux facturerait la marge au fournisseur.
            'providerUnitCost' => (float) $this->provider_unit_cost,
            'providerTotalCost' => (float) $this->provider_total_cost,
            'weight' => (float) $this->weight,
            'volume' => (float) $this->volume,
            'packageCount' => (int) $this->package_count,
            'status' => $this->status?->value ?? $this->status,
            'customerName' => $this->whenLoaded('order', fn () => $this->order->customer?->name),
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
