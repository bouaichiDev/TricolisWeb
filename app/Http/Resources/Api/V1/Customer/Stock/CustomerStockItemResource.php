<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Stock;

use App\Modules\CustomerPortal\Services\CustomerStatusLabels;
use App\Modules\CustomerPortal\Services\Stock\StockQuantity;
use App\Modules\Stock\Models\StockItem;
use App\Shared\Database\MorphMap;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un article en stock du client, avec ses totaux agrégés (§10).
 *
 * Les totaux viennent de `CustomerStockItemsQuery::withTotals()` (sommes
 * SQL des balances) ; aucune colonne de total n'existe sur `StockItem`. Le
 * lien catalogue ne montre que l'article du catalogue du client lui-même.
 *
 * @mixin StockItem
 */
class CustomerStockItemResource extends JsonResource
{
    /**
     * @param  array<string, bool>  $actions
     */
    public function __construct(StockItem $item, private readonly array $actions)
    {
        parent::__construct($item);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $catalogItem = $this->relationLoaded('catalogItem') ? $this->catalogItem : null;

        return [
            'id' => $this->id,
            'catalogItemId' => $this->catalog_item_id,
            'catalogItem' => $catalogItem === null ? null : ['id' => $catalogItem->id, 'articleCode' => $catalogItem->article_code, 'name' => $catalogItem->name],
            'articleCode' => $this->article_code,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'status' => app(CustomerStatusLabels::class)->present(MorphMap::STOCK_ITEM, $this->status),
            'stock' => StockQuantity::totals($this->quantity_total, $this->reserved_total, $this->available_total),
            'actions' => $this->actions,
        ];
    }
}
