<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Shared\Support\AuditContext;
use App\Shared\Support\Money;

/**
 * Recalcule les totaux d'un décompte fournisseur.
 *
 * ```text
 * subtotal = Σ totalCost
 * total    = subtotal + taxTotal
 * ```
 *
 * `taxTotal` n'est **pas** calculé : il reste celui qui a été saisi. Le §21
 * interdit d'inventer une TVA fournisseur, et le modèle ne porte aucun taux au
 * niveau ligne — `ProviderSettlementLine` n'a ni `taxRate` ni `taxAmount`, et le
 * §18 interdit de les ajouter. Sans taux, aucune taxe n'est dérivable.
 */
final readonly class RecalculateProviderSettlementTotals
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(ProviderSettlement $settlement, ?AuditContext $context = null): ProviderSettlement
    {
        $subtotal = Money::sum($settlement->lines()->pluck('total_cost'));
        $taxTotal = Money::round((string) $settlement->tax_total);

        $before = $settlement->only(['subtotal', 'total']);

        $settlement->update([
            'subtotal' => $subtotal,
            'total' => Money::round(Money::add($subtotal, $taxTotal)),
        ]);

        $after = $settlement->fresh()->only(['subtotal', 'total']);

        if ($context !== null && $before !== $after) {
            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'provider_settlement_totals.recalculated',
                $settlement,
                $before,
                $after,
                null,
                $context->ipAddress,
            );
        }

        return $settlement->fresh();
    }
}
