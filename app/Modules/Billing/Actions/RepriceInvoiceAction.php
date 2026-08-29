<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Pricing\Actions\CalculateOrderServicePrice;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Recalcule les prix d'une facture au brouillon.
 *
 * **Explicite, et jamais automatique** (§169AM). Recalculer à chaque ouverture
 * ferait changer une facture sous les yeux de celui qui la relit ; ici, il le
 * demande.
 *
 * **L'écart se montre avant de s'appliquer.** Le mode aperçu rend les
 * différences sans rien écrire : accepter un nouveau prix est une décision, et
 * une facture qui bouge en silence ne se contrôle plus.
 *
 * Une facture clôturée n'est jamais recalculée (§169AN) : ses prix sont
 * historiques, et le §22 la fige — le contrôleur refuse avant d'arriver ici.
 *
 * Les lignes libres — sans prestation — sont laissées telles quelles : aucun
 * barème ne les gouverne, et les toucher effacerait une saisie délibérée.
 */
final readonly class RepriceInvoiceAction
{
    public function __construct(
        private CalculateOrderServicePrice $calculate,
        private CalculateInvoiceLineTotals $totals,
        private RecalculateInvoiceTotals $invoiceTotals,
        private WriteAuditLog $audit,
    ) {}

    /**
     * @return array<int, array<string, mixed>> une entrée par ligne concernée
     */
    public function execute(Invoice $invoice, AuditContext $context, bool $apply): array
    {
        $invoice->loadMissing('lines.orderService');

        $changes = [];

        foreach ($invoice->lines as $line) {
            $change = $this->evaluate($line, $invoice->organization_id);

            if ($change !== null) {
                $changes[] = $change;
            }
        }

        if (! $apply || $changes === []) {
            return $changes;
        }

        DB::transaction(function () use ($invoice, $changes, $context): void {
            foreach ($changes as $change) {
                $this->applyTo($invoice, $change);
            }

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'invoice.repriced',
                $invoice,
                null,
                ['lines' => count($changes)],
                null,
                $context->ipAddress,
            );
        });

        $this->invoiceTotals->execute($invoice->fresh(), $context);

        return $changes;
    }

    /**
     * Ce que cette ligne deviendrait.
     *
     * Rend `null` quand rien ne bouge : une ligne dont le prix est déjà le bon
     * n'a pas à figurer dans un écart.
     *
     * @return array<string, mixed>|null
     */
    private function evaluate(InvoiceLine $line, string $organizationId): ?array
    {
        $service = $line->orderService;

        if ($service === null) {
            return null;
        }

        $outcome = $this->calculate->execute($service, $organizationId, record: false);

        // Une ligne dont le tarif a disparu garde son prix : le §169BO refuse
        // qu'un echec de calcul devienne un nouveau montant.
        if (! $outcome->priced) {
            return [
                'lineId' => $line->id,
                'lineNumber' => $line->line_number,
                'description' => $line->description,
                'currentUnitPrice' => (string) $line->unit_price,
                'newUnitPrice' => null,
                'reason' => $outcome->reason,
            ];
        }

        if (bccomp((string) $line->unit_price, (string) $outcome->amount, 2) === 0) {
            return null;
        }

        return [
            'lineId' => $line->id,
            'lineNumber' => $line->line_number,
            'description' => $line->description,
            'currentUnitPrice' => (string) $line->unit_price,
            'newUnitPrice' => $outcome->amount,
            'reason' => null,
            'scope' => $outcome->pricing?->scope(),
            'formula' => $outcome->pricing?->rule->formula,
        ];
    }

    /**
     * @param  array<string, mixed>  $change
     */
    private function applyTo(Invoice $invoice, array $change): void
    {
        if ($change['newUnitPrice'] === null) {
            return;
        }

        $line = $invoice->lines->firstWhere('id', $change['lineId']);

        if ($line === null) {
            return;
        }

        $totals = $this->totals->execute(
            (string) $line->quantity,
            (string) $change['newUnitPrice'],
            (string) $line->discount_rate,
            (string) $line->tax_rate,
        );

        $line->update([
            'unit_price' => $change['newUnitPrice'],
            'total_excluding_tax' => $totals['total_excluding_tax'],
            'total_including_tax' => $totals['total_including_tax'],
        ]);

        // Le calcul retenu s'historise : celui de l'apercu ne l'etait pas.
        $this->calculate->execute($line->orderService, $invoice->organization_id);
    }
}
