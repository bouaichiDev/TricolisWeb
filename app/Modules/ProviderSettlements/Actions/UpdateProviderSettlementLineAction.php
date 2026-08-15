<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\ProviderSettlements\DTOs\UpdateProviderSettlementLineData;
use App\Modules\ProviderSettlements\Models\ProviderSettlementLine;
use App\Modules\ProviderSettlements\Services\SettlementScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modifie une ligne de décompte.
 *
 * Toute modification de `quantity` ou `unitCost` recalcule `totalCost`, puis les
 * totaux du décompte.
 */
final readonly class UpdateProviderSettlementLineAction
{
    public function __construct(
        private SettlementScopeGuard $guard,
        private CalculateProviderSettlementLineTotal $calculator,
        private RecalculateProviderSettlementTotals $totals,
        private WriteAuditLog $audit,
    ) {}

    public function execute(ProviderSettlementLine $line, UpdateProviderSettlementLineData $data, AuditContext $context): ProviderSettlementLine
    {
        $attributes = $data->attributes->all();

        if ($attributes === []) {
            return $line;
        }

        $settlement = $line->settlement;

        if (($attributes['order_service_id'] ?? null) !== null) {
            $this->guard->orderService($attributes['order_service_id'], $settlement->provider);
        }

        if (array_intersect(array_keys($attributes), UpdateProviderSettlementLineData::RECALCULATES) !== []) {
            $attributes['total_cost'] = $this->calculator->execute(
                (string) ($attributes['quantity'] ?? $line->quantity),
                (string) ($attributes['unit_cost'] ?? $line->unit_cost),
            );
        }

        $updated = DB::transaction(function () use ($line, $attributes, $context): ProviderSettlementLine {
            $before = $line->only(array_keys($attributes));
            $line->update($attributes);
            $after = $line->fresh()->only(array_keys($attributes));

            if ($before !== $after) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'provider_settlement_line.updated',
                    $line,
                    $before,
                    $after,
                    null,
                    $context->ipAddress,
                );
            }

            return $line->fresh();
        });

        $this->totals->execute($settlement, $context);

        return $updated;
    }
}
