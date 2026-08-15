<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Billing\Exceptions\InvoiceLineRequired;
use App\Modules\Billing\Models\InvoiceLine;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Retire une ligne de facture.
 *
 * Refusé si c'est la dernière : la cardinalité `1..*` laisserait une facture
 * que le diagramme n'autorise pas. Pour la vider, il faut la supprimer.
 *
 * Le snapshot d'adresse disparaît avec la ligne — c'est la composition
 * `InvoiceLine *-- InvoiceLineAddressSnapshot`, portée par la cascade.
 */
final readonly class RemoveInvoiceLineAction
{
    public function __construct(
        private RecalculateInvoiceTotals $totals,
        private WriteAuditLog $audit,
    ) {}

    public function execute(InvoiceLine $line, AuditContext $context): void
    {
        $invoice = $line->invoice;

        if ($invoice->lines()->count() <= 1) {
            throw InvoiceLineRequired::lastLine();
        }

        DB::transaction(function () use ($line, $context): void {
            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'invoice_line.deleted',
                $line,
                $line->only(['invoice_id', 'line_number', 'description', 'total_including_tax']),
                null,
                null,
                $context->ipAddress,
            );

            $line->delete();
        });

        $this->totals->execute($invoice, $context);
    }
}
