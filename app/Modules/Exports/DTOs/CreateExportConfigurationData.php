<?php

declare(strict_types=1);

namespace App\Modules\Exports\DTOs;

use App\Shared\Support\Secret;

/**
 * Données de création d'une configuration d'export.
 *
 * Le mot de passe arrive **en clair** et ne va pas plus loin que
 * `toAttributes()`, qui le chiffre. Il n'est jamais journalisé ni audité.
 */
final readonly class CreateExportConfigurationData
{
    /**
     * @param  array<mixed>|null  $settings
     */
    public function __construct(
        public string $customerId,
        public string $name,
        public string $exportType,
        public string $format,
        public string $transport,
        public ?string $host = null,
        public ?int $port = null,
        public ?string $username = null,
        public ?string $password = null,
        public ?string $remoteDirectory = null,
        public ?string $fileNamePattern = null,
        public ?string $encoding = null,
        public ?string $frequency = null,
        public ?array $settings = null,
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
            exportType: $validated['exportType'],
            format: $validated['format'],
            transport: $validated['transport'],
            host: $validated['host'] ?? null,
            port: isset($validated['port']) ? (int) $validated['port'] : null,
            username: $validated['username'] ?? null,
            password: $validated['password'] ?? null,
            remoteDirectory: $validated['remoteDirectory'] ?? null,
            fileNamePattern: $validated['fileNamePattern'] ?? null,
            encoding: $validated['encoding'] ?? null,
            frequency: $validated['frequency'] ?? null,
            settings: $validated['settings'] ?? null,
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
            'export_type' => $this->exportType,
            'format' => $this->format,
            'transport' => $this->transport,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'encrypted_password' => Secret::encrypt($this->password),
            'remote_directory' => $this->remoteDirectory,
            'file_name_pattern' => $this->fileNamePattern,
            'encoding' => $this->encoding,
            'frequency' => $this->frequency,
            'settings' => $this->settings,
            'is_active' => $this->isActive,
        ];
    }
}
