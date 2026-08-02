<?php

declare(strict_types=1);

namespace App\Modules\Stock\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Stock\DTOs\CreateStockLocationData;
use App\Modules\Stock\Models\StockLocation;
use App\Modules\Stock\Services\StockScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Crée un emplacement de stock.
 *
 * Le parent, s'il est fourni, doit relever du même dépôt — un rayon ne peut pas
 * appartenir à l'allée d'un autre entrepôt.
 */
final readonly class CreateStockLocationAction
{
    public function __construct(
        private StockScopeGuard $guard,
        private ValidateStockLocationHierarchy $hierarchy,
        private WriteAuditLog $audit,
    ) {}

    public function execute(CreateStockLocationData $data, AuditContext $context): StockLocation
    {
        $this->guard->depot($data->depotId, $context->organizationId);

        if ($data->parentLocationId !== null) {
            $this->hierarchy->execute(null, $data->parentLocationId, $data->depotId);
        }

        return DB::transaction(function () use ($data, $context): StockLocation {
            $location = StockLocation::create($data->toAttributes());

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'stock_location.created',
                $location,
                null,
                $location->only(['depot_id', 'parent_location_id', 'location_code', 'status']),
                null,
                $context->ipAddress,
            );

            return $location;
        });
    }
}
