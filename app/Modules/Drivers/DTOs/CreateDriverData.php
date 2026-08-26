<?php

declare(strict_types=1);

namespace App\Modules\Drivers\DTOs;

/**
 * Données de création d'un chauffeur.
 *
 * `organizationId` n'est pas dans le payload : il vient du contexte actif, et
 * l'Action vérifie qu'il coïncide avec celui du fournisseur.
 *
 * `providerId` est **facultatif** : un transporteur emploie ses propres
 * chauffeurs, sans passer par un fournisseur.
 *
 * `userId` est rempli par l'Action, qui crée le compte : il n'est pas saisi.
 */
final readonly class CreateDriverData
{
    public function __construct(
        public string $code,
        public string $name,
        public string $status,
        public string $firstName,
        public string $lastName,
        public string $email,
        public ?string $providerId = null,
        public ?string $phone = null,
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
            name: trim($validated['firstName'].' '.$validated['lastName']),
            status: $validated['status'],
            firstName: $validated['firstName'],
            lastName: $validated['lastName'],
            email: $validated['email'],
            providerId: $validated['providerId'] ?? null,
            phone: $validated['phone'] ?? null,
            addressId: $validated['addressId'] ?? null,
            contactId: $validated['contactId'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $organizationId, ?string $userId = null): array
    {
        return [
            'organization_id' => $organizationId,
            'provider_id' => $this->providerId,
            'user_id' => $userId,
            'address_id' => $this->addressId,
            'contact_id' => $this->contactId,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
        ];
    }
}
