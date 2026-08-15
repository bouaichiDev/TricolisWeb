<?php

declare(strict_types=1);

namespace App\Modules\Exports\DTOs;

/**
 * Données de création d'un export.
 *
 * `customerId` n'y figure pas : il est **forcé** à celui de la configuration.
 * Le §24 impose de vérifier leur cohérence ; le déduire est plus sûr que le
 * comparer.
 *
 * `fileName`, `storagePath`, `generatedAt`, `sentAt` et `errorMessage` non plus :
 * ils appartiennent au traitement, pas à la demande.
 */
final readonly class CreateExportJobData
{
    public function __construct(
        public string $configurationId,
        public string $status,
        public ?string $entityType = null,
        public ?string $entityId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            configurationId: $validated['configurationId'],
            status: $validated['status'],
            entityType: $validated['entityType'] ?? null,
            entityId: $validated['entityId'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $customerId): array
    {
        return [
            'customer_id' => $customerId,
            'configuration_id' => $this->configurationId,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'status' => $this->status,
            'attempt_count' => 0,
        ];
    }
}
