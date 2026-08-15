<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Billing\DTOs\CreateInvoiceData;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\BillingScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Crée une facture **et ses lignes** dans la même transaction.
 *
 * `Invoice "1" *-- "1..*" InvoiceLine` : une facture vide n'existe pas au
 * modèle. Si une ligne échoue — service hors périmètre, numéro dupliqué,
 * service déjà facturé — rien ne subsiste : ni facture, ni ligne partielle, ni
 * snapshot orphelin.
 *
 * Les références sont vérifiées **avant** la transaction, pour que l'échec soit
 * un 422 lisible plutôt qu'un rollback muet.
 */
final readonly class CreateInvoiceAction
{
    public function __construct(
        private BillingScopeGuard $guard,
        private AddInvoiceLineAction $addLine,
        private RecalculateInvoiceTotals $totals,
        private WriteAuditLog $audit,
    ) {}

    public function execute(CreateInvoiceData $data, AuditContext $context, string $now): Invoice
    {
        $customer = $this->guard->customer($data->customerId, $context->organizationId);

        $invoice = DB::transaction(function () use ($data, $customer, $context, $now): Invoice {
            $invoice = Invoice::create($data->toAttributes($customer->organization_id, $now))->refresh();

            foreach ($data->lines as $index => $line) {
                $this->addLine->execute($invoice, $line, $context, "lines.{$index}", audit: false);
            }

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'invoice.created',
                $invoice,
                null,
                $invoice->only(['customer_id', 'invoice_number', 'invoice_date', 'currency_code', 'status']),
                null,
                $context->ipAddress,
            );

            return $invoice;
        });

        return $this->totals->execute($invoice);
    }
}
