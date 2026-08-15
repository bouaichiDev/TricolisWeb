<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\ProviderSettlements\Exceptions\SettlementLineRequired;
use App\Modules\ProviderSettlements\Models\ProviderSettlementLine;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Retire une ligne de décompte, sauf si c'est la dernière.
 */
final readonly class RemoveProviderSettlementLineAction
{
    public function __construct(
        private RecalculateProviderSettlementTotals $totals,
        private WriteAuditLog $audit,
    ) {}

    public function execute(ProviderSettlementLine $line, AuditContext $context): void
    {
        $settlement = $line->settlement;

        if ($settlement->lines()->count() <= 1) {
            throw SettlementLineRequired::lastLine();
        }

        DB::transaction(function () use ($line, $context): void {
            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'provider_settlement_line.deleted',
                $line,
                $line->only(['settlement_id', 'description', 'total_cost']),
                null,
                null,
                $context->ipAddress,
            );

            $line->delete();
        });

        $this->totals->execute($settlement, $context);
    }
}
