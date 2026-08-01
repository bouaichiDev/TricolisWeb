<?php

declare(strict_types=1);

namespace App\Modules\Drivers\DTOs;

/**
 * Données de création d'un chauffeur.
 *
 * `organizationId` n'est pas dans le payload : il vient du contexte actif, et
 * l'Action vérifie qu'il coïncide avec celui du fournisseur.
 */
final readonly class CreateDriverData
{
    public function __construct(
        public string $providerId,
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
            providerId: $validated['providerId'],
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
            'provider_id' => $this->providerId,
            'address_id' => $this->addressId,
            'contact_id' => $this->contactId,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
        ];
    }
}
