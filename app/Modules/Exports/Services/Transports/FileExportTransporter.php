<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services\Transports;

use App\Modules\Exports\Enums\ExportTransport;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Services\RemoteTargetGuard;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Dépose la facture sur un serveur FTP ou SFTP.
 *
 * **Un seul transporteur pour les deux.** Ils ne diffèrent que par le pilote du
 * système de fichiers ; en écrire deux dupliquerait la validation du chemin,
 * celle de l'hôte et le traitement des erreurs — et le §76 refuse par ailleurs
 * une table par transport.
 *
 * Le disque est construit à la volée depuis la configuration, jamais déclaré
 * dans `filesystems.php` : les destinations appartiennent aux clients et se
 * créent sans redéploiement.
 *
 * Le mot de passe est déchiffré au dernier moment, passé au pilote, et
 * n'apparaît dans aucun message — le §124 l'exige.
 */
final readonly class FileExportTransporter implements ExportTransporter
{
    private const int DEFAULT_TIMEOUT = 30;

    public function __construct(private RemoteTargetGuard $guard) {}

    public function send(
        CustomerExportConfiguration $configuration,
        string $fileName,
        string $contents,
        string $contentType,
    ): void {
        $host = (string) $configuration->host;
        $this->guard->assertFileHost($host);

        $directory = $this->guard->safePath((string) $configuration->remote_directory);
        $target = $directory === '' ? $fileName : $directory.'/'.$fileName;

        try {
            $written = $this->disk($configuration, $host)->put($target, $contents);
        } catch (Throwable $exception) {
            // Le message du pilote peut contenir l'URL de connexion, donc le
            // mot de passe. Seule sa classe est reprise.
            throw new RuntimeException(sprintf(
                'Transfert vers %s impossible (%s).',
                strtoupper($configuration->transport->value),
                class_basename($exception),
            ));
        }

        if ($written === false) {
            throw new RuntimeException('Le serveur distant a refusé le fichier.');
        }
    }

    private function disk(CustomerExportConfiguration $configuration, string $host): FilesystemAdapter
    {
        $driver = $configuration->transport === ExportTransport::SFTP ? 'sftp' : 'ftp';

        return Storage::build(array_filter([
            'driver' => $driver,
            'host' => $host,
            'port' => $configuration->port,
            'username' => $configuration->username,
            'password' => $configuration->password(),
            'timeout' => self::DEFAULT_TIMEOUT,
            // Le repertoire fait partie du chemin, pas de la racine : le
            // laisser ici empecherait de verifier ce qu'on ecrit.
            'root' => '',
        ], static fn ($value): bool => $value !== null && $value !== ''));
    }
}
