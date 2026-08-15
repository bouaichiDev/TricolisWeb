<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

/**
 * Copie d'adresse à figer sur une ligne de facture.
 *
 * Tous les champs sont facultatifs : un snapshot doit savoir figer une adresse
 * incomplète.
 */
final readonly class CreateInvoiceLineAddressSnapshotData
{
    public function __construct(
        public ?string $addressCode = null,
        public ?string $name = null,
        public ?string $addressLine1 = null,
        public ?string $addressLine2 = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $country = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            addressCode: $validated['addressCode'] ?? null,
            name: $validated['name'] ?? null,
            addressLine1: $validated['addressLine1'] ?? null,
            addressLine2: $validated['addressLine2'] ?? null,
            postalCode: $validated['postalCode'] ?? null,
            city: $validated['city'] ?? null,
            country: $validated['country'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $invoiceLineId): array
    {
        return [
            'invoice_line_id' => $invoiceLineId,
            'address_code' => $this->addressCode,
            'name' => $this->name,
            'address_line1' => $this->addressLine1,
            'address_line2' => $this->addressLine2,
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'country' => $this->country,
        ];
    }
}
