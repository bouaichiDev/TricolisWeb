<?php

declare(strict_types=1);

namespace App\Modules\Integrations\DTOs;

/**
 * Données de création d'une configuration d'import.
 */
final readonly class CreateImportConfigurationData
{
    /**
     * @param  array<mixed>|null  $mapping
     * @param  array<mixed>|null  $validationRules
     */
    public function __construct(
        public string $customerId,
        public string $name,
        public string $sourceType,
        public string $fileFormat,
        public ?array $mapping = null,
        public ?array $validationRules = null,
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
            sourceType: $validated['sourceType'],
            fileFormat: $validated['fileFormat'],
            mapping: $validated['mapping'] ?? null,
            validationRules: $validated['validationRules'] ?? null,
            isActive: (bool) ($validated['isActive'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'customer_id' => $this->customerId,
            'name' => $this->name,
            'source_type' => $this->sourceType,
            'file_format' => $this->fileFormat,
            'mapping' => $this->mapping,
            'validation_rules' => $this->validationRules,
            'is_active' => $this->isActive,
        ];
    }
}
