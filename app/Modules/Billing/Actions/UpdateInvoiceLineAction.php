<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Billing\DTOs\UpdateInvoiceLineData;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Billing\Services\BillingScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modifie une ligne de facture.
 *
 * Toute modification de `quantity`, `unitPrice`, `discountRate` ou `taxRate`
 * déclenche le recalcul des deux totaux de la ligne, puis des trois totaux de
 * la facture. Les totaux ne peuvent donc jamais diverger de leurs composantes.
 */
final readonly class UpdateInvoiceLineAction
{
    public function __construct(
        private BillingScopeGuard $guard,
        private CalculateInvoiceLineTotals $calculator,
        private RecalculateInvoiceTotals $totals,
        private WriteAuditLog $audit,
    ) {}

    public function execute(InvoiceLine $line, UpdateInvoiceLineData $data, AuditContext $context): InvoiceLine
    {
        $attributes = $data->attributes->all();

        if ($attributes === []) {
            return $line;
        }

        $invoice = $line->invoice;
        $customer = $invoice->customer;

        $serviceId = array_key_exists('order_service_id', $attributes)
            ? $attributes['order_service_id']
            : $line->order_service_id;

        $service = $serviceId !== null
            ? $this->guard->orderService($serviceId, $customer)
            : null;

        $orderId = array_key_exists('order_id', $attributes) ? $attributes['order_id'] : $line->order_id;

        if ($orderId !== null) {
            $this->guard->order($orderId, $customer);
        }

        $this->guard->assertOrderMatchesService($orderId, $service);

        $attributes = $this->withRecalculatedTotals($line, $attributes);

        $updated = DB::transaction(function () use ($line, $attributes, $context): InvoiceLine {
            $before = $line->only(array_keys($attributes));
            $line->update($attributes);
            $after = $line->fresh()->only(array_keys($attributes));

            if ($before !== $after) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'invoice_line.updated',
                    $line,
                    $before,
                    $after,
                    null,
                    $context->ipAddress,
                );
            }

            return $line->fresh();
        });

        $this->totals->execute($invoice, $context);

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withRecalculatedTotals(InvoiceLine $line, array $attributes): array
    {
        if (array_intersect(array_keys($attributes), UpdateInvoiceLineData::RECALCULATES) === []) {
            return $attributes;
        }

        return array_merge($attributes, $this->calculator->execute(
            (string) ($attributes['quantity'] ?? $line->quantity),
            (string) ($attributes['unit_price'] ?? $line->unit_price),
            (string) ($attributes['discount_rate'] ?? $line->discount_rate),
            (string) ($attributes['tax_rate'] ?? $line->tax_rate),
        ));
    }
}
