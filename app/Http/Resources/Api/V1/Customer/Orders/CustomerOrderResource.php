<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Orders;

use App\Modules\CustomerPortal\Services\CustomerStatusLabels;
use App\Modules\Orders\Models\Order;
use App\Shared\Database\MorphMap;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Le détail d'une commande, **pour le client**.
 *
 * Construit champ par champ, jamais en recopiant `OrderDetailResource` :
 * celle-ci est faite pour l'opérateur et expose remarque interne, agence,
 * prix d'achat et de vente, coûts fournisseur. Rien de cela ne sort ici.
 *
 * `updatedAt` est la version à renvoyer lors d'une modification.
 *
 * @mixin Order
 */
class CustomerOrderResource extends JsonResource
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
            'workerRemark' => $this->worker_remark,
            'status' => app(CustomerStatusLabels::class)->present(MorphMap::ORDER, $this->status),
            'source' => $this->source?->value,
            'weight' => $this->weight,
            'volume' => $this->volume,
            'packageCount' => (int) $this->package_count,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'actions' => $this->actions,
            'lines' => CustomerOrderLineResource::collection($this->whenLoaded('lines')),
            'packages' => CustomerPackageResource::collection($this->whenLoaded('packages')),
            'services' => CustomerOrderServiceResource::collection($this->whenLoaded('orderServices')),
        ];
    }
}
