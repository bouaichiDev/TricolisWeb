<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Sources;

use App\Modules\Exports\Services\RemoteTargetGuard;
use App\Modules\Integrations\DTOs\InboundFileConnection;
use App\Modules\Integrations\Exceptions\PermanentIntegrationFailure;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Shared\Support\Secret;
use RuntimeException;

/**
 * Les connexions FTP/SFTP de relève, portées par la configuration d'import
 * (`connection` + `encrypted_password`, depuis le 2026-10-10) et réglées
 * depuis le back-office.
 *
 * Le chemin, le motif et l'hôte sont contrôlés comme ceux d'un export — pas de
 * remontée d'arborescence, pas d'hôte interne — à l'enregistrement **et** à
 * chaque relève. Le mot de passe est chiffré ; aucune réponse d'API, aucun
 * journal ne le rend.
 */
final readonly class InboundFileConnections
{
    /** Ce que `connection` peut contenir : jamais de secret. */
    private const array FIELDS = ['host', 'port', 'username', 'directory', 'filePattern', 'afterImport', 'archiveDirectory', 'actorEmail'];

    public function __construct(private RemoteTargetGuard $guard) {}

    public function isConfigured(CustomerImportConfiguration $configuration): bool
    {
        return trim((string) ($configuration->connection['host'] ?? '')) !== '';
    }

    /**
     * Enregistre la connexion. `$passwordProvided` faux : le mot de passe
     * existant est gardé ; vrai avec `null` : il est effacé.
     *
     * @param  array<string, mixed>  $validated  validé par `SaveFileConnectionRequest`
     *
     * @throws RuntimeException hôte interne ou invalide
     */
    public function save(CustomerImportConfiguration $configuration, array $validated, bool $passwordProvided, #[\SensitiveParameter] ?string $password): void
    {
        $this->guard->assertFileHost((string) $validated['host'], isset($validated['port']) ? (int) $validated['port'] : null);

        $connection = array_intersect_key($validated, array_flip(self::FIELDS));
        $connection['directory'] = $this->guard->safePath((string) ($validated['directory'] ?? ''));

        if (isset($connection['archiveDirectory'])) {
            $connection['archiveDirectory'] = $this->guard->safePath((string) $connection['archiveDirectory']);
        }

        $attributes = ['connection' => $connection];

        if ($passwordProvided) {
            $attributes['encrypted_password'] = Secret::encrypt($password === '' ? null : $password);
        }

        $configuration->forceFill($attributes)->save();
    }

    /**
     * @throws PermanentIntegrationFailure connexion absente ou invalide
     */
    public function for(CustomerImportConfiguration $configuration): InboundFileConnection
    {
        if (! $this->isConfigured($configuration)) {
            throw new PermanentIntegrationFailure('Aucune connexion FTP/SFTP n’est enregistrée pour cette configuration.');
        }

        $declared = (array) $configuration->connection;
        $driver = ImportSourceKind::of($configuration);

        if (! in_array($driver, [ImportSourceKind::FTP, ImportSourceKind::SFTP], true)) {
            throw new PermanentIntegrationFailure('Le protocole de relève doit être ftp ou sftp.');
        }

        $host = trim((string) $declared['host']);
        $port = isset($declared['port']) ? (int) $declared['port'] : null;

        try {
            $this->guard->assertFileHost($host, $port);
        } catch (RuntimeException $exception) {
            throw new PermanentIntegrationFailure($exception->getMessage());
        }

        $directory = $this->guard->safePath((string) ($declared['directory'] ?? ''));
        $after = in_array($declared['afterImport'] ?? null, InboundFileConnection::AFTER_IMPORT, true) ? $declared['afterImport'] : 'archive';

        return new InboundFileConnection(
            driver: $driver,
            host: $host,
            port: $port,
            username: isset($declared['username']) ? (string) $declared['username'] : null,
            password: Secret::decrypt($configuration->encrypted_password),
            directory: $directory,
            filePattern: $this->pattern($declared['filePattern'] ?? null, (string) $configuration->file_format),
            afterImport: $after,
            archiveDirectory: $this->guard->safePath((string) ($declared['archiveDirectory'] ?? ltrim($directory.'/archive', '/'))),
            actorEmail: isset($declared['actorEmail']) ? (string) $declared['actorEmail'] : null,
        );
    }

    /**
     * Un motif de nom de fichier, jamais un chemin : `*.xml`, `orders_*.xml`.
     */
    private function pattern(mixed $declared, string $fileFormat): string
    {
        if (is_string($declared) && preg_match('/^[A-Za-z0-9._*?\-]{1,64}$/', $declared) === 1) {
            return $declared;
        }

        $format = strtolower(trim($fileFormat));

        return preg_match('/^[a-z0-9]{1,8}$/', $format) === 1 ? "*.{$format}" : '*';
    }
}
