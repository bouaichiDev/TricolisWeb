<?php

declare(strict_types=1);

namespace App\Modules\Stock\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Stock\DTOs\UpdateStockLocationData;
use App\Modules\Stock\Models\StockLocation;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modifie un emplacement, réorganisation comprise.
 *
 * Changer de parent est légitime — on réorganise un entrepôt — mais passe par
 * le contrôle de cycle : c'est en déplaçant une branche sous l'une de ses
 * propres feuilles qu'on casse une hiérarchie sans s'en apercevoir.
 */
final readonly class UpdateStockLocationAction
{
    public function __construct(
        private ValidateStockLocationHierarchy $hierarchy,
        private WriteAuditLog $audit,
    ) {}

    public function execute(StockLocation $location, UpdateStockLocationData $data, AuditContext $context): StockLocation
    {
        $attributes = $data->attributes->all();

        if ($attributes === []) {
            return $location;
        }

        if (($attributes['parent_location_id'] ?? null) !== null) {
            $this->hierarchy->execute($location, $attributes['parent_location_id'], $location->depot_id);
        }

        return DB::transaction(function () use ($location, $attributes, $context): StockLocation {
            $before = $location->only(array_keys($attributes));
            $location->update($attributes);
            $after = $location->fresh()->only(array_keys($attributes));

            if ($before !== $after) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'stock_location.updated',
                    $location,
                    $before,
                    $after,
                    null,
                    $context->ipAddress,
                );
            }

            return $location->fresh();
        });
    }
}
