<?php

declare(strict_types=1);

namespace App\Modules\Exports\DTOs;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;

/**
 * La facture telle qu'elle part chez le client.
 *
 * **Une forme canonique, pas une table.** Le §63 le demande explicitement : ce
 * DTO se calcule à l'envoi et ne se stocke nulle part. Les formats — JSON, XML —
 * s'en nourrissent tous les deux, ce qui évite de dupliquer la génération dans
 * chaque transporteur comme le §85 l'interdit.
 *
 * **Les identifiants internes n'y sont pas.** Le §64 privilégie les références
 * métier : un client externe reconnaît `INV-2026-001` et sa propre référence de
 * commande, pas un ULID de vingt-six caractères.
 *
 * Les montants sont des **chaînes**. Un décimal passé en flottant JSON se relit
 * `108.10000000000001` chez le destinataire, et une facture ne se négocie pas à
 * la virgule près.
 */
final readonly class InvoiceExportData
{
    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function __construct(
        public string $invoiceNumber,
        public string $invoiceDate,
        public ?string $periodFrom,
        public ?string $periodTo,
        public string $currencyCode,
        public string $subtotal,
        public string $taxTotal,
        public string $total,
        public ?string $externalReference,
        public ?string $remark,
        public array $lines,
    ) {}

    /**
     * Construit la forme canonique depuis la facture et ses lignes chargées.
     *
     * L'adresse vient du **cliché** pris à la création de la ligne, jamais de
     * l'adresse vivante : le §13 veut qu'une facture d'août continue d'afficher
     * l'adresse d'août, même si le client a déménagé depuis.
     */
    public static function from(Invoice $invoice): self
    {
        return new self(
            invoiceNumber: (string) $invoice->invoice_number,
            invoiceDate: $invoice->invoice_date?->toDateString() ?? '',
            periodFrom: $invoice->period_from?->toDateString(),
            periodTo: $invoice->period_to?->toDateString(),
            currencyCode: (string) $invoice->currency_code,
            subtotal: self::amount($invoice->subtotal),
            taxTotal: self::amount($invoice->tax_total),
            total: self::amount($invoice->total),
            externalReference: $invoice->external_reference,
            remark: $invoice->remark,
            lines: $invoice->lines
                ->sortBy('line_number')
                ->map(static fn (InvoiceLine $line): array => self::line($line))
                ->values()
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function line(InvoiceLine $line): array
    {
        $snapshot = $line->relationLoaded('addressSnapshot') ? $line->addressSnapshot : null;

        return array_filter([
            'lineNumber' => (int) $line->line_number,
            'serviceCode' => $line->service_code,
            'description' => (string) $line->description,
            'customerOrderReference' => $line->customer_order_reference,
            'quantity' => self::amount($line->quantity, 3),
            'unitPrice' => self::amount($line->unit_price),
            'discountRate' => self::amount($line->discount_rate),
            'taxRate' => self::amount($line->tax_rate),
            'totalExcludingTax' => self::amount($line->total_excluding_tax),
            'totalIncludingTax' => self::amount($line->total_including_tax),
            'serviceCompletedAt' => $line->service_completed_at?->toIso8601String(),
            'address' => $snapshot === null ? null : array_filter([
                'addressCode' => $snapshot->address_code,
                'name' => $snapshot->name,
                'addressLine1' => $snapshot->address_line1,
                'addressLine2' => $snapshot->address_line2,
                'postalCode' => $snapshot->postal_code,
                'city' => $snapshot->city,
                'country' => $snapshot->country,
            ], static fn ($value): bool => $value !== null),
        ], static fn ($value): bool => $value !== null && $value !== []);
    }

    /** Chaîne à décimales fixes : un flottant JSON dérive à la relecture. */
    private static function amount(mixed $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, '.', '');
    }

    /**
     * La facture entière, prête à sérialiser.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'invoiceNumber' => $this->invoiceNumber,
            'invoiceDate' => $this->invoiceDate,
            'periodFrom' => $this->periodFrom,
            'periodTo' => $this->periodTo,
            'currencyCode' => $this->currencyCode,
            'subtotal' => $this->subtotal,
            'taxTotal' => $this->taxTotal,
            'total' => $this->total,
            'externalReference' => $this->externalReference,
            'remark' => $this->remark,
            'lines' => $this->lines,
        ], static fn ($value): bool => $value !== null);
    }
}
