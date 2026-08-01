<?php

declare(strict_types=1);

namespace App\Modules\Providers\DTOs;

/**
 * Données de création d'un fournisseur.
 *
 * L'organisation n'est pas dans le payload : elle vient du contexte actif, pour
 * qu'un appelant ne puisse pas créer un fournisseur ailleurs.
 */
final readonly class CreateProviderData
{
    public function __construct(
        public string $code,
        public string $name,
        public string $providerType,
        public string $status,
        public ?int $legacyId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            code: $validated['code'],
            name: $validated['name'],
            providerType: $validated['providerType'],
            status: $validated['status'],
            legacyId: $validated['legacyId'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $organizationId): array
    {
        return [
            'organization_id' => $organizationId,
            'legacy_id' => $this->legacyId,
            'code' => $this->code,
            'name' => $this->name,
            'provider_type' => $this->providerType,
            'status' => $this->status,
        ];
    }
}
