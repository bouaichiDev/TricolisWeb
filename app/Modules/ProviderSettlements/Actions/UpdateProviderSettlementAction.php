<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\ProviderSettlements\DTOs\UpdateProviderSettlementData;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modifie l'en-tête d'un décompte.
 *
 * `taxTotal` est modifiable — il est saisi, pas dérivé — et son changement
 * entraîne le recalcul de `total`.
 */
final readonly class UpdateProviderSettlementAction
{
    public function __construct(
        private RecalculateProviderSettlementTotals $totals,
        private WriteAuditLog $audit,
    ) {}

    public function execute(ProviderSettlement $settlement, UpdateProviderSettlementData $data, AuditContext $context): ProviderSettlement
    {
        $attributes = $data->attributes->all();

        if ($attributes === []) {
            return $settlement;
        }

        $updated = DB::transaction(function () use ($settlement, $attributes, $context): ProviderSettlement {
            $before = $settlement->only(array_keys($attributes));
            $settlement->update($attributes);
            $after = $settlement->fresh()->only(array_keys($attributes));

            if ($before !== $after) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'provider_settlement.updated',
                    $settlement,
                    $before,
                    $after,
                    null,
                    $context->ipAddress,
                );
            }

            return $settlement->fresh();
        });

        return array_key_exists('tax_total', $attributes)
            ? $this->totals->execute($updated, $context)
            : $updated;
    }
}
