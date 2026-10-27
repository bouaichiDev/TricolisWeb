<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Sources;

use App\Modules\Integrations\DTOs\InboundFileConnection;
use App\Modules\Integrations\Exceptions\PermanentIntegrationFailure;
use App\Modules\Integrations\Exceptions\TransientIntegrationFailure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Throwable;

/**
 * Les fichiers candidats d'un serveur FTP/SFTP, lus puis rangés.
 *
 * - **Lister** : le seul répertoire configuré, non récursif, les noms qui
 *   correspondent au motif, par ordre alphabétique — un dépôt daté se relève
 *   dans l'ordre —, plafonné par passe.
 * - **Lire** : taille contrôlée avant le téléchargement.
 * - **Ranger** : uniquement après un import sans erreur (§13 : ne jamais
 *   supprimer le fichier distant avant succès complet), selon `afterImport`.
 *
 * Une panne du serveur est passagère (le Job réessaie) ; son message
 * technique, qui peut contenir l'hôte ou l'identifiant, n'est pas repris.
 */
final readonly class RemoteFileSource
{
    public function __construct(private InboundFilesystems $filesystems) {}

    public function open(InboundFileConnection $connection): Filesystem
    {
        return $this->filesystems->open($connection);
    }

    /**
     * @return list<string>
     */
    public function candidates(Filesystem $disk, InboundFileConnection $connection): array
    {
        try {
            $files = $disk->files($connection->directory);
        } catch (Throwable) {
            throw new TransientIntegrationFailure("Le serveur {$connection->driver} est injoignable ou refuse la connexion.");
        }

        $matching = array_values(array_filter(
            $files,
            static fn (string $path): bool => fnmatch($connection->filePattern, basename($path)),
        ));

        sort($matching);

        return array_slice($matching, 0, (int) config('customer_integrations.pull.max_files_per_run', 50));
    }

    public function read(Filesystem $disk, string $path): string
    {
        try {
            $size = $disk->size($path);
            $maxBytes = (int) config('customer_integrations.files.max_bytes', 5 * 1024 * 1024);

            if ($size > $maxBytes) {
                throw new PermanentIntegrationFailure(sprintf('Le fichier %s dépasse %d Ko : il est laissé sur le serveur.', basename($path), intdiv($maxBytes, 1024)));
            }

            $contents = $disk->get($path);
        } catch (PermanentIntegrationFailure $failure) {
            throw $failure;
        } catch (Throwable) {
            throw new TransientIntegrationFailure('Le fichier '.basename($path).' n’a pas pu être téléchargé.');
        }

        if (! is_string($contents)) {
            throw new TransientIntegrationFailure('Le fichier '.basename($path).' n’a pas pu être téléchargé.');
        }

        return $contents;
    }

    /**
     * Range un fichier importé sans erreur. Rend le sort réservé au fichier.
     */
    public function finish(Filesystem $disk, InboundFileConnection $connection, string $path): string
    {
        try {
            return match ($connection->afterImport) {
                'delete' => $disk->delete($path) ? 'deleted' : 'kept',
                'archive' => $disk->move($path, ltrim($connection->archiveDirectory.'/'.basename($path), '/')) ? 'archived' : 'kept',
                default => 'kept',
            };
        } catch (Throwable) {
            // Le fichier reste en place : la passe suivante le relira, et
            // l'idempotence de l'import empêche tout doublon.
            return 'kept';
        }
    }
}
