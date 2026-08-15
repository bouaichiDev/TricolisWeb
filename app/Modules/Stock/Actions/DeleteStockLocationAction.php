<?php

declare(strict_types=1);

namespace App\Modules\Stock\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Stock\Exceptions\StockConflict;
use App\Modules\Stock\Models\StockLocation;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Supprime un emplacement vide.
 *
 * Refusé s'il porte des enfants, du stock ou une réservation active. Les soldes
 * à zéro partent avec lui.
 */
final readonly class DeleteStockLocationAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(StockLocation $location, AuditContext $context): void
    {
        if ($location->isInUse()) {
            throw StockConflict::locationInUse();
        }

        DB::transaction(function () use ($location, $context): void {
            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'stock_location.deleted',
                $location,
                $location->only(['depot_id', 'parent_location_id', 'location_code', 'status']),
                null,
                null,
                $context->ipAddress,
            );

            $location->balances()->delete();
            $location->delete();
        });
    }
}
