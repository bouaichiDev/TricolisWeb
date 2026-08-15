<?php

declare(strict_types=1);

namespace App\Modules\Stock\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Stock\DTOs\CreateStockItemData;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Services\StockScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Crée un article de stock.
 *
 * L'article de catalogue, s'il est fourni, doit relever du même client :
 * rattacher la référence d'un autre client rendrait le catalogue incohérent.
 */
final readonly class CreateStockItemAction
{
    public function __construct(
        private StockScopeGuard $guard,
        private WriteAuditLog $audit,
    ) {}

    public function execute(CreateStockItemData $data, AuditContext $context): StockItem
    {
        $customer = $this->guard->customer($data->customerId, $context->organizationId);

        if ($data->catalogItemId !== null) {
            $this->guard->catalogItem($data->catalogItemId, $customer);
        }

        return DB::transaction(function () use ($data, $context): StockItem {
            $item = StockItem::create($data->toAttributes());

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'stock_item.created',
                $item,
                null,
                $item->only(['customer_id', 'article_code', 'barcode', 'status']),
                null,
                $context->ipAddress,
            );

            return $item;
        });
    }
}
