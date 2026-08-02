<?php

declare(strict_types=1);

namespace App\Modules\Stock\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Stock\Exceptions\StockConflict;
use App\Modules\Stock\Models\StockItem;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Supprime un article de stock qui n'engage rien.
 *
 * Refusé dès qu'il porte du stock, un historique de mouvements ou une
 * réservation : effacer la référence rendrait ces lignes illisibles, et les
 * clés étrangères en `RESTRICT` le bloqueraient de toute façon — autant
 * renvoyer un message métier.
 *
 * Les soldes à zéro sont supprimés avec l'article : ce sont des lignes vides.
 */
final readonly class DeleteStockItemAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(StockItem $item, AuditContext $context): void
    {
        if ($item->isInUse()) {
            throw StockConflict::itemInUse();
        }

        DB::transaction(function () use ($item, $context): void {
            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'stock_item.deleted',
                $item,
                $item->only(['customer_id', 'article_code', 'barcode', 'status']),
                null,
                null,
                $context->ipAddress,
            );

            $item->balances()->delete();
            $item->delete();
        });
    }
}
