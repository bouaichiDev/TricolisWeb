<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

/**
 * Données de création d'une facture, lignes comprises.
 *
 * `Invoice "1" *-- "1..*" InvoiceLine` interdit une facture vide : les lignes
 * font partie de la création et sont écrites dans la même transaction.
 *
 * Les trois totaux ne sont pas acceptés : ils sont dérivés des lignes.
 */
final readonly class CreateInvoiceData
{
    /**
     * @param  list<CreateInvoiceLineData>  $lines
     */
    public function __construct(
        public string $customerId,
        public string $invoiceNumber,
        public string $invoiceDate,
        public string $currencyCode,
        public string $status,
        public array $lines,
        public ?string $periodFrom = null,
        public ?string $periodTo = null,
        public ?string $externalReference = null,
        public ?string $remark = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            customerId: $validated['customerId'],
            invoiceNumber: $validated['invoiceNumber'],
            invoiceDate: $validated['invoiceDate'],
            currencyCode: $validated['currencyCode'],
            status: $validated['status'],
            lines: array_map(
                static fn (array $line): CreateInvoiceLineData => CreateInvoiceLineData::fromValidated($line),
                $validated['lines'],
            ),
            periodFrom: $validated['periodFrom'] ?? null,
            periodTo: $validated['periodTo'] ?? null,
            externalReference: $validated['externalReference'] ?? null,
            remark: $validated['remark'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $organizationId, string $createdAt): array
    {
        return [
            'organization_id' => $organizationId,
            'customer_id' => $this->customerId,
            'invoice_number' => $this->invoiceNumber,
            'invoice_date' => $this->invoiceDate,
            'period_from' => $this->periodFrom,
            'period_to' => $this->periodTo,
            'currency_code' => $this->currencyCode,
            'external_reference' => $this->externalReference,
            'remark' => $this->remark,
            'status' => $this->status,
            'created_at' => $createdAt,
        ];
    }
}
