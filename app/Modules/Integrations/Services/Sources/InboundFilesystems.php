<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Sources;

use App\Modules\Integrations\DTOs\InboundFileConnection;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Ouvre le serveur FTP/SFTP d'une connexion de relève.
 *
 * Isolé pour une seule raison : les tests remplacent cette fabrique par un
 * disque local simulé, et aucune suite ne touche un vrai serveur.
 */
class InboundFilesystems
{
    public function open(InboundFileConnection $connection): Filesystem
    {
        return Storage::build(array_filter([
            'driver' => $connection->driver,
            'host' => $connection->host,
            'port' => $connection->port,
            'username' => $connection->username,
            'password' => $connection->password,
            'root' => '',
            'timeout' => (int) config('customer_integrations.pull.timeout_seconds', 30),
            'passive' => $connection->driver === 'ftp' ? true : null,
            'ssl' => false,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
