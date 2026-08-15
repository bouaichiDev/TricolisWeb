<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Billing\DTOs\CreateInvoiceLineData;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Billing\Services\BillingScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute une ligne à une facture, avec son éventuel snapshot d'adresse.
 *
 * Appelée aussi bien par la création atomique — d'où le paramètre `$audit`, qui
 * évite de journaliser N créations de ligne au sein d'une création de facture —
 * que par la route `POST /invoices/{invoice}/lines`.
 *
 * Le service et la commande sont contrôlés contre le **client de la facture**,
 * jamais contre celui du payload : le client n'est pas modifiable.
 */
final readonly class AddInvoiceLineAction
{
    public function __construct(
        private BillingScopeGuard $guard,
        private CalculateInvoiceLineTotals $calculator,
        private RecalculateInvoiceTotals $totals,
        private WriteAuditLog $audit,
    ) {}

    public function execute(
        Invoice $invoice,
        CreateInvoiceLineData $data,
        AuditContext $context,
        string $fieldPrefix = '',
        bool $audit = true,
    ): InvoiceLine {
        $customer = $invoice->customer;
        $prefix = $fieldPrefix === '' ? '' : $fieldPrefix.'.';

        $service = $data->orderServiceId !== null
            ? $this->guard->orderService($data->orderServiceId, $customer, $prefix.'orderServiceId')
            : null;

        if ($data->orderId !== null) {
            $this->guard->order($data->orderId, $customer, $prefix.'orderId');
        }

        $this->guard->assertOrderMatchesService($data->orderId, $service, $prefix.'orderId');

        $totals = $this->calculator->execute(
            $data->quantity,
            $data->unitPrice,
            $data->discountRate,
            $data->taxRate,
        );

        $line = DB::transaction(function () use ($invoice, $data, $totals, $context, $audit): InvoiceLine {
            $line = InvoiceLine::create($data->toAttributes($invoice->id, $totals));

            if ($data->addressSnapshot !== null) {
                $line->addressSnapshot()->create($data->addressSnapshot->toAttributes($line->id));
            }

            if ($audit) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'invoice_line.created',
                    $line,
                    null,
                    $line->only(['invoice_id', 'line_number', 'description', 'total_including_tax']),
                    null,
                    $context->ipAddress,
                );
            }

            return $line;
        });

        if ($audit) {
            $this->totals->execute($invoice, $context);
        }

        return $line;
    }
}
