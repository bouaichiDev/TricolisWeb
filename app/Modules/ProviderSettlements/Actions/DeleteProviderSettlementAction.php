<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Supprime un décompte et ses lignes.
 *
 * Le §34 évoque un refus « si une règle de conservation existe » : aucune n'est
 * définie, et le §15 interdit d'inventer les valeurs de `status`. La suppression
 * reste protégée par la seule permission `provider_settlements.delete`.
 */
final readonly class DeleteProviderSettlementAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(ProviderSettlement $settlement, AuditContext $context): void
    {
        DB::transaction(function () use ($settlement, $context): void {
            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'provider_settlement.deleted',
                $settlement,
                $settlement->only(['provider_id', 'settlement_number', 'total', 'status']),
                null,
                null,
                $context->ipAddress,
            );

            $settlement->lines()->delete();
            $settlement->delete();
        });
    }
}
