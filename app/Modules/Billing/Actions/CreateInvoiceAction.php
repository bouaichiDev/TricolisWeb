<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Billing\DTOs\CreateInvoiceData;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\BillingScopeGuard;
use App\Shared\Support\AuditContext;
use Carbon\CarbonImmutable;
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
 *
 * **Le numéro s'attribue ici quand il n'est pas fourni**, et dans la
 * transaction : c'est la seule façon de tenir le verrou du compteur jusqu'à
 * l'écriture de la facture. L'attribuer avant laisserait un numéro consommé par
 * une création qui échoue ensuite sur une ligne.
 */
final readonly class CreateInvoiceAction
{
    public function __construct(
        private BillingScopeGuard $guard,
        private GenerateInvoiceNumber $numbers,
        private AddInvoiceLineAction $addLine,
        private RecalculateInvoiceTotals $totals,
        private WriteAuditLog $audit,
    ) {}

    public function execute(CreateInvoiceData $data, AuditContext $context, string $now): Invoice
    {
        $customer = $this->guard->customer($data->customerId, $context->organizationId);

        $invoice = DB::transaction(function () use ($data, $customer, $context, $now): Invoice {
            $attributes = $data->toAttributes($customer->organization_id, $now);
            $attributes = $this->numbered($attributes, $customer->organization_id);

            $invoice = Invoice::create($attributes)->refresh();

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

    /**
     * Complète les numéros laissés vides.
     *
     * **La référence externe reprend le numéro de facture.** Elle désigne le
     * document chez le destinataire ; lui inventer un code différent créerait un
     * second nom pour la même chose, qui ne pointerait vers aucun système. Une
     * organisation qui tient sa propre série la saisit, et elle est conservée.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function numbered(array $attributes, string $organizationId): array
    {
        $year = CarbonImmutable::parse((string) $attributes['invoice_date'])->year;

        if (($attributes['invoice_number'] ?? null) === null || $attributes['invoice_number'] === '') {
            $attributes['invoice_number'] = $this->numbers->execute($organizationId, $year);
        }

        if (($attributes['external_reference'] ?? null) === null || $attributes['external_reference'] === '') {
            $attributes['external_reference'] = $attributes['invoice_number'];
        }

        return $attributes;
    }
}
