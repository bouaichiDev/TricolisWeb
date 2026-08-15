<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Billing\Models\Invoice;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Supprime une facture et ses lignes.
 *
 * Le §34 évoque un refus si « son statut indique un état final selon une règle
 * existante ». **Aucune règle n'existe** : le §6 interdit d'inventer les valeurs
 * de `status`, et les interpréter reviendrait à décider lesquelles sont
 * définitives — ce que personne n'a arrêté. La suppression reste donc protégée
 * par la seule permission `invoices.delete`.
 *
 * Les lignes et leurs snapshots disparaissent par cascade : c'est le
 * comportement d'un agrégat, pas une cascade silencieuse — la facture entière
 * est ce que l'appelant a demandé à supprimer.
 */
final readonly class DeleteInvoiceAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(Invoice $invoice, AuditContext $context): void
    {
        DB::transaction(function () use ($invoice, $context): void {
            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'invoice.deleted',
                $invoice,
                $invoice->only(['customer_id', 'invoice_number', 'invoice_date', 'total', 'status']),
                null,
                null,
                $context->ipAddress,
            );

            $invoice->lines()->delete();
            $invoice->delete();
        });
    }
}
