<?php

declare(strict_types=1);

namespace App\Modules\Stock\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Stock\DTOs\UpdateStockItemData;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Services\StockScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modifie un article de stock.
 *
 * Le client n'est pas modifiable : l'article de catalogue est donc recontrôlé
 * contre le client **enregistré**, jamais contre un client fourni.
 */
final readonly class UpdateStockItemAction
{
    public function __construct(
        private StockScopeGuard $guard,
        private WriteAuditLog $audit,
    ) {}

    public function execute(StockItem $item, UpdateStockItemData $data, AuditContext $context): StockItem
    {
        $attributes = $data->attributes->all();

        if ($attributes === []) {
            return $item;
        }

        if (($attributes['catalog_item_id'] ?? null) !== null) {
            $this->guard->catalogItem($attributes['catalog_item_id'], $item->customer);
        }

        return DB::transaction(function () use ($item, $attributes, $context): StockItem {
            $before = $item->only(array_keys($attributes));
            $item->update($attributes);
            $after = $item->fresh()->only(array_keys($attributes));

            if ($before !== $after) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'stock_item.updated',
                    $item,
                    $before,
                    $after,
                    null,
                    $context->ipAddress,
                );
            }

            return $item->fresh();
        });
    }
}
