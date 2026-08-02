<?php

declare(strict_types=1);

namespace App\Modules\Integrations\DTOs;

/**
 * Données de création d'un accès API client.
 *
 * `apiKeyHash` n'y figure pas : la clé est **générée** par l'Action, jamais
 * fournie. Accepter une empreinte en entrée permettrait d'installer une clé
 * choisie par l'appelant.
 */
final readonly class CreateApiConfigurationData
{
    /**
     * @param  list<string>|null  $allowedIps
     * @param  list<string>|null  $permissions
     */
    public function __construct(
        public string $customerId,
        public string $name,
        public ?array $allowedIps = null,
        public ?array $permissions = null,
        public bool $isActive = true,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            customerId: $validated['customerId'],
            name: $validated['name'],
            allowedIps: $validated['allowedIps'] ?? null,
            permissions: $validated['permissions'] ?? null,
            isActive: (bool) ($validated['isActive'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $apiKeyHash): array
    {
        return [
            'customer_id' => $this->customerId,
            'name' => $this->name,
            'api_key_hash' => $apiKeyHash,
            'allowed_ips' => $this->allowedIps,
            'permissions' => $this->permissions,
            'is_active' => $this->isActive,
        ];
    }
}
