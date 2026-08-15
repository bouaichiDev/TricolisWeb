<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\ProviderSettlements\DTOs\CreateProviderSettlementLineData;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Modules\ProviderSettlements\Models\ProviderSettlementLine;
use App\Modules\ProviderSettlements\Services\SettlementScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute une ligne à un décompte fournisseur.
 *
 * Le service est contrôlé contre le **fournisseur du décompte** : s'il a été
 * planifié sur une tournée affectée à un autre fournisseur, l'ajout est refusé.
 */
final readonly class AddProviderSettlementLineAction
{
    public function __construct(
        private SettlementScopeGuard $guard,
        private CalculateProviderSettlementLineTotal $calculator,
        private RecalculateProviderSettlementTotals $totals,
        private WriteAuditLog $audit,
    ) {}

    public function execute(
        ProviderSettlement $settlement,
        CreateProviderSettlementLineData $data,
        AuditContext $context,
        string $fieldPrefix = '',
        bool $audit = true,
    ): ProviderSettlementLine {
        $prefix = $fieldPrefix === '' ? '' : $fieldPrefix.'.';

        if ($data->orderServiceId !== null) {
            $this->guard->orderService($data->orderServiceId, $settlement->provider, $prefix.'orderServiceId');
        }

        $totalCost = $this->calculator->execute($data->quantity, $data->unitCost);

        $line = DB::transaction(function () use ($settlement, $data, $totalCost, $context, $audit): ProviderSettlementLine {
            $line = ProviderSettlementLine::create($data->toAttributes($settlement->id, $totalCost));

            if ($audit) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'provider_settlement_line.created',
                    $line,
                    null,
                    $line->only(['settlement_id', 'description', 'total_cost']),
                    null,
                    $context->ipAddress,
                );
            }

            return $line;
        });

        if ($audit) {
            $this->totals->execute($settlement, $context);
        }

        return $line;
    }
}
