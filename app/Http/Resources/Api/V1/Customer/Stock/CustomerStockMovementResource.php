<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Stock;

use App\Modules\CustomerPortal\Services\Stock\StockQuantity;
use App\Modules\Stock\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un mouvement d'un article du client (§16), en lecture seule.
 *
 * `movementType` est la valeur enregistrée par l'interne, sans traduction
 * inventée. `direction` se **déduit** des emplacements — seule une source :
 * sortie ; seule une destination : entrée ; les deux : transfert — comme le
 * fait l'écran interne. L'auteur interne n'est pas exposé.
 *
 * @mixin StockMovement
 */
class CustomerStockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stockItem' => ['id' => $this->stockItem?->id, 'articleCode' => $this->stockItem?->article_code, 'description' => $this->stockItem?->description],
            'movementType' => $this->movement_type,
            'direction' => match (true) {
                $this->source_location_id !== null && $this->destination_location_id !== null => 'transfer',
                $this->source_location_id !== null => 'exit',
                default => 'entry',
            },
            'quantity' => StockQuantity::of($this->quantity),
            'sourceLocation' => CustomerStockLocation::present($this->sourceLocation),
            'destinationLocation' => CustomerStockLocation::present($this->destinationLocation),
            'sourceEntityType' => $this->source_entity_type,
            'sourceEntityId' => $this->source_entity_id,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
