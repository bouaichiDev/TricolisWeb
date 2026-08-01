<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

/**
 * Données de création d'une ligne de facture.
 *
 * `totalExcludingTax` et `totalIncludingTax` n'y figurent pas : ils sont
 * calculés par `CalculateInvoiceLineTotals`. Le §11 interdit de faire confiance
 * aux totaux envoyés.
 */
final readonly class CreateInvoiceLineData
{
    public function __construct(
        public int $lineNumber,
        public string $description,
        public string $quantity,
        public string $unitPrice,
        public string $status,
        public string $discountRate = '0',
        public string $taxRate = '0',
        public ?string $orderServiceId = null,
        public ?string $orderId = null,
        public ?string $serviceCode = null,
        public ?string $customerOrderReference = null,
        public ?string $serviceCompletedAt = null,
        public ?CreateInvoiceLineAddressSnapshotData $addressSnapshot = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            lineNumber: (int) $validated['lineNumber'],
            description: $validated['description'],
            quantity: (string) $validated['quantity'],
            unitPrice: (string) $validated['unitPrice'],
            status: $validated['status'],
            discountRate: (string) ($validated['discountRate'] ?? '0'),
            taxRate: (string) ($validated['taxRate'] ?? '0'),
            orderServiceId: $validated['orderServiceId'] ?? null,
            orderId: $validated['orderId'] ?? null,
            serviceCode: $validated['serviceCode'] ?? null,
            customerOrderReference: $validated['customerOrderReference'] ?? null,
            serviceCompletedAt: $validated['serviceCompletedAt'] ?? null,
            addressSnapshot: isset($validated['addressSnapshot'])
                ? CreateInvoiceLineAddressSnapshotData::fromValidated($validated['addressSnapshot'])
                : null,
        );
    }

    /**
     * @param  array{total_excluding_tax: string, total_including_tax: string}  $totals
     * @return array<string, mixed>
     */
    public function toAttributes(string $invoiceId, array $totals): array
    {
        return [
            'invoice_id' => $invoiceId,
            'order_service_id' => $this->orderServiceId,
            'order_id' => $this->orderId,
            'line_number' => $this->lineNumber,
            'service_code' => $this->serviceCode,
            'description' => $this->description,
            'customer_order_reference' => $this->customerOrderReference,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'discount_rate' => $this->discountRate,
            'tax_rate' => $this->taxRate,
            'total_excluding_tax' => $totals['total_excluding_tax'],
            'total_including_tax' => $totals['total_including_tax'],
            'service_completed_at' => $this->serviceCompletedAt,
            'status' => $this->status,
        ];
    }
}
