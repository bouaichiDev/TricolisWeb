<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Billing\DTOs\UpdateInvoiceData;
use App\Modules\Billing\Models\Invoice;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modifie l'en-tête d'une facture.
 *
 * Ni le client, ni les totaux : le premier rendrait les lignes incohérentes
 * avec leur commande d'origine, les seconds sont dérivés des lignes.
 */
final readonly class UpdateInvoiceAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(Invoice $invoice, UpdateInvoiceData $data, AuditContext $context): Invoice
    {
        $attributes = $data->attributes->all();

        if ($attributes === []) {
            return $invoice;
        }

        return DB::transaction(function () use ($invoice, $attributes, $context): Invoice {
            $before = $invoice->only(array_keys($attributes));
            $invoice->update($attributes);
            $after = $invoice->fresh()->only(array_keys($attributes));

            if ($before !== $after) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'invoice.updated',
                    $invoice,
                    $before,
                    $after,
                    null,
                    $context->ipAddress,
                );
            }

            return $invoice->fresh();
        });
    }
}
