<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Orders;

use App\Modules\CustomerPortal\Services\CustomerStatusLabels;
use App\Modules\Orders\Models\Order;
use App\Shared\Database\MorphMap;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une commande dans la liste du portail : de quoi la reconnaître et savoir
 * ce qu'on peut en faire, rien de plus.
 *
 * `actions` est calculé par `CustomerOrderCapabilities` pour toute la page
 * d'un coup et transmis en `additional` : la ressource ne l'évalue pas.
 *
 * @mixin Order
 */
class CustomerOrderListResource extends JsonResource
{
    /**
     * @param  array<string, bool>  $actions
     */
    public function __construct(Order $order, private readonly array $actions)
    {
        parent::__construct($order);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->order_number,
            'customerReference' => $this->customer_reference,
            'externalReference' => $this->external_reference,
            'orderDate' => $this->order_date?->toIso8601String(),
            'firstRequestedDate' => $this->first_requested_date,
            'status' => app(CustomerStatusLabels::class)->present(MorphMap::ORDER, $this->status),
            'source' => $this->source?->value,
            'servicesCount' => (int) ($this->order_services_count ?? 0),
            'packageCount' => (int) $this->package_count,
            'weight' => $this->weight,
            'createdAt' => $this->created_at?->toIso8601String(),
            'actions' => $this->actions,
        ];
    }
}
