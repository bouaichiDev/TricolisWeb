<?php

declare(strict_types=1);

namespace App\Modules\Providers\DTOs;

/**
 * Données de création d'un fournisseur.
 *
 * L'organisation n'est pas dans le payload : elle vient du contexte actif, pour
 * qu'un appelant ne puisse pas créer un fournisseur ailleurs.
 *
 * Adresse et contact sont facultatifs (`0..1` au diagramme).
 */
final readonly class CreateProviderData
{
    public function __construct(
        public string $code,
        public string $name,
        public string $status,
        public ?string $addressId = null,
        public ?string $contactId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            code: $validated['code'],
            name: $validated['name'],
            status: $validated['status'],
            addressId: $validated['addressId'] ?? null,
            contactId: $validated['contactId'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $organizationId): array
    {
        return [
            'organization_id' => $organizationId,
            'address_id' => $this->addressId,
            'contact_id' => $this->contactId,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
        ];
    }
}
