<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Billing\Models\Invoice;
use App\Shared\Support\AuditContext;
use App\Shared\Support\Money;

/**
 * Recalcule les trois totaux d'une facture à partir de ses lignes.
 *
 * ```text
 * subtotal = Σ totalExcludingTax
 * total    = Σ totalIncludingTax
 * taxTotal = total − subtotal
 * ```
 *
 * `taxTotal` est **déduit**, jamais sommé séparément : le déduire garantit
 * l'identité `subtotal + taxTotal = total`, qu'une somme indépendante pourrait
 * violer d'un centime après arrondis.
 *
 * Le §14 interdit d'inventer d'autres frais ou remises globales — il n'y en a
 * aucun.
 */
final readonly class RecalculateInvoiceTotals
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(Invoice $invoice, ?AuditContext $context = null): Invoice
    {
        $lines = $invoice->lines()->get(['total_excluding_tax', 'total_including_tax']);

        $subtotal = Money::sum($lines->pluck('total_excluding_tax'));
        $total = Money::sum($lines->pluck('total_including_tax'));
        $taxTotal = Money::round(Money::subtract($total, $subtotal));

        $before = $invoice->only(['subtotal', 'tax_total', 'total']);

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $total,
        ]);

        $after = $invoice->fresh()->only(['subtotal', 'tax_total', 'total']);

        if ($context !== null && $before !== $after) {
            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'invoice_totals.recalculated',
                $invoice,
                $before,
                $after,
                null,
                $context->ipAddress,
            );
        }

        return $invoice->fresh();
    }
}
