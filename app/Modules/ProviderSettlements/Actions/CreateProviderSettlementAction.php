<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\ProviderSettlements\DTOs\CreateProviderSettlementData;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Modules\ProviderSettlements\Services\SettlementScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Crée un décompte fournisseur **et ses lignes** dans la même transaction.
 *
 * `ProviderSettlement "1" *-- "1..*" ProviderSettlementLine` : un décompte vide
 * n'existe pas. Rollback complet si une ligne échoue.
 */
final readonly class CreateProviderSettlementAction
{
    public function __construct(
        private SettlementScopeGuard $guard,
        private AddProviderSettlementLineAction $addLine,
        private RecalculateProviderSettlementTotals $totals,
        private WriteAuditLog $audit,
    ) {}

    public function execute(CreateProviderSettlementData $data, AuditContext $context): ProviderSettlement
    {
        $provider = $this->guard->provider($data->providerId, $context->organizationId);

        $settlement = DB::transaction(function () use ($data, $provider, $context): ProviderSettlement {
            $settlement = ProviderSettlement::create(
                $data->toAttributes($provider->organization_id),
            )->refresh();

            foreach ($data->lines as $index => $line) {
                $this->addLine->execute($settlement, $line, $context, "lines.{$index}", audit: false);
            }

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'provider_settlement.created',
                $settlement,
                null,
                $settlement->only(['provider_id', 'settlement_number', 'status']),
                null,
                $context->ipAddress,
            );

            return $settlement;
        });

        return $this->totals->execute($settlement);
    }
}
