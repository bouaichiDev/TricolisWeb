<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Billing\DTOs\CreateInvoiceLineData;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Billing\Services\BillingScopeGuard;
use App\Modules\Billing\Services\InvoiceLinePricing;
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
 *
 * **Le prix vient du barème, pas de l'écran** (§169AK), dès que la ligne porte
 * une prestation. Reprendre le montant envoyé par React laisserait deux
 * calculs vivre en parallèle, et c'est la facture qui aurait tort. Une ligne
 * libre — sans prestation — garde le prix saisi : elle ne relève d'aucun
 * barème.
 */
final readonly class AddInvoiceLineAction
{
    public function __construct(
        private BillingScopeGuard $guard,
        private CalculateInvoiceLineTotals $calculator,
        private InvoiceLinePricing $pricing,
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

        $unitPrice = $service === null
            ? $data->unitPrice
            : $this->pricing->unitPrice(
                $service,
                $invoice->organization_id,
                $data->unitPrice,
                $data->priceOverride,
                $prefix.'unitPrice',
            );

        $totals = $this->calculator->execute(
            $data->quantity,
            $unitPrice,
            $data->discountRate,
            $data->taxRate,
        );

        $line = DB::transaction(function () use ($invoice, $data, $unitPrice, $totals, $context, $audit): InvoiceLine {
            $line = InvoiceLine::create(
                ['unit_price' => $unitPrice] + $data->toAttributes($invoice->id, $totals),
            );

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
