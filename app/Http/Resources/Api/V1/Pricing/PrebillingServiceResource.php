<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Pricing;

use App\Modules\Orders\Models\OrderService;
use App\Modules\Pricing\DTOs\PriceOutcome;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une prestation à facturer, avec le tarif que le moteur lui trouve.
 *
 * Le §169AI veut qu'on voie d'où vient le prix : la portée, la formule, la
 * zone. Un tarif surprenant se discute alors avec des arguments, plutôt qu'en
 * ouvrant la base.
 *
 * @mixin OrderService
 */
class PrebillingServiceResource extends JsonResource
{
    public function __construct(OrderService $resource, private readonly PriceOutcome $outcome)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serviceNumber' => $this->service_number,
            'orderId' => $this->order_id,
            'orderNumber' => $this->order?->order_number,
            'customerReference' => $this->order?->customer_reference,
            'customerId' => $this->order?->customer_id,
            'customerName' => $this->order?->customer?->name,
            'serviceCode' => $this->service?->code,
            'serviceName' => $this->service?->name,
            'requestedDate' => $this->requested_date?->toDateString(),
            'currencyCode' => $this->order?->currency_code,

            'weight' => (float) $this->weight,
            'volume' => (float) $this->volume,
            'packageCount' => (int) $this->package_count,
            'quantity' => (float) $this->quantity,
            'postalCode' => $this->address?->postal_code,
            'city' => $this->address?->city,

            // Le prix deja porte par la prestation, et celui que le bareme
            // donnerait : les montrer cote a cote fait voir l'ecart.
            'currentUnitPrice' => (float) $this->customer_unit_price,

            'priced' => $this->outcome->priced,
            'calculatedPrice' => $this->outcome->amount,
            'reason' => $this->outcome->reason,
            'scope' => $this->outcome->pricing?->scope(),
            'formula' => $this->outcome->pricing?->rule->formula,
            'priceRuleCode' => $this->outcome->pricing?->rule->code,
            'zone' => $this->outcome->pricing?->row?->label,
        ];
    }
}
