<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Orders;

use App\Http\Resources\Api\V1\Agencies\AgencyResource;
use App\Http\Resources\Api\V1\Agencies\DepotResource;
use App\Http\Resources\Api\V1\Customers\CustomerCompactResource;
use App\Http\Resources\Api\V1\Packages\PackageResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Services\StatusMachine;
use App\Shared\Database\MorphMap;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Commande complète.
 *
 * Tous les blocs passent par `whenLoaded` : l'appelant ne paie que ce que le
 * contrôleur a réellement chargé.
 *
 * @mixin Order
 */
class OrderDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var StatusMachine $machine */
        $machine = Container::getInstance()->make(StatusMachine::class);

        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'orderNumber' => $this->order_number,
            'externalReference' => $this->external_reference,
            'customerReference' => $this->customer_reference,
            'orderType' => $this->order_type,
            'groupCode' => $this->group_code,
            'parentOrderId' => $this->parent_order_id,
            'orderDate' => $this->order_date,
            'source' => $this->source?->value,
            'status' => $this->status?->value,
            'statusLabel' => $this->status?->label(),
            // Comportement et transitions viennent du referentiel : c'est
            // l'administrateur plateforme qui dessine le cycle de vie, et une
            // regle figee ici contredirait son ecran des le premier statut
            // ajoute.
            'allowsContentChanges' => $machine->allowsContentChanges(MorphMap::ORDER, $this->status?->value),
            'allowedTransitions' => array_map(
                static fn (Status $status): string => $status->code,
                $machine->transitionsFrom(MorphMap::ORDER, $this->status?->value),
            ),
            'internalRemark' => $this->internal_remark,
            'workerRemark' => $this->worker_remark,
            'weight' => $this->weight,
            'volume' => $this->volume,
            'packageCount' => $this->package_count,
            'currencyCode' => $this->currency_code,
            'customerId' => $this->customer_id,
            'agencyId' => $this->agency_id,
            'depotId' => $this->depot_id,
            'customer' => new CustomerCompactResource($this->whenLoaded('customer')),
            'agency' => new AgencyResource($this->whenLoaded('agency')),
            'depot' => new DepotResource($this->whenLoaded('depot')),
            'lines' => OrderLineResource::collection($this->whenLoaded('lines')),
            'packages' => PackageResource::collection($this->whenLoaded('packages')),
            'services' => OrderServiceResource::collection($this->whenLoaded('orderServices')),
            'createdBy' => $this->created_by,
            'updatedBy' => $this->updated_by,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
