<?php

declare(strict_types=1);

namespace App\Modules\Integrations\DTOs;

/**
 * Une connexion FTP/SFTP de relève, telle que sa configuration la porte.
 *
 * Le mot de passe ne quitte jamais cet objet autrement que vers le client
 * FTP/SFTP : aucune méthode ne le rend sous forme lisible pour un journal ou
 * une réponse (`describe()` l'omet).
 */
final readonly class InboundFileConnection
{
    /** Après import réussi : déplacer, supprimer ou laisser le fichier. */
    public const array AFTER_IMPORT = ['archive', 'delete', 'keep'];

    public function __construct(
        public string $driver,
        public string $host,
        public ?int $port,
        public ?string $username,
        #[\SensitiveParameter]
        public ?string $password,
        public string $directory,
        public string $filePattern,
        public string $afterImport,
        public string $archiveDirectory,
        public ?string $actorEmail,
    ) {}

    /**
     * Un `dump()` ou un journal d'exception ne montre pas le secret.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return $this->describe();
    }

    /**
     * Ce qui peut être montré : jamais le secret.
     *
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        return [
            'driver' => $this->driver,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'directory' => $this->directory,
            'filePattern' => $this->filePattern,
            'afterImport' => $this->afterImport,
            'archiveDirectory' => $this->afterImport === 'archive' ? $this->archiveDirectory : null,
            'actorEmail' => $this->actorEmail,
            'hasCredentials' => $this->password !== null && $this->password !== '',
        ];
    }
}
