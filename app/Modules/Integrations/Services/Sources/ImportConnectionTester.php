<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Sources;

use App\Modules\Integrations\Exceptions\PermanentIntegrationFailure;
use App\Modules\Integrations\Exceptions\TransientIntegrationFailure;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use RuntimeException;

/**
 * « Tester la connexion » — un appel réel, **sans rien importer**.
 *
 * - pull REST : l'URL de santé configurée (`healthPath`) ou la première page
 *   de la source, statut HTTP seul — le contenu n'est ni lu ni gardé ;
 * - FTP/SFTP : connexion et liste du répertoire, nombre de fichiers
 *   candidats — aucun n'est téléchargé.
 *
 * Le verdict dit ce qui s'est passé ; une configuration qui ne peut pas être
 * testée le dit aussi, au lieu d'afficher un faux succès.
 */
final readonly class ImportConnectionTester
{
    public function __construct(
        private RestPullConnections $rest,
        private RestPullSource $pull,
        private InboundFileConnections $files,
        private RemoteFileSource $remote,
    ) {}

    /**
     * @return array{ok: bool, message: string, details: array<string, mixed>}
     */
    public function test(CustomerImportConfiguration $configuration): array
    {
        try {
            return match (ImportSourceKind::of($configuration)) {
                ImportSourceKind::REST_PULL => $this->rest($configuration),
                ImportSourceKind::FTP, ImportSourceKind::SFTP => $this->files($configuration),
                default => ['ok' => false, 'message' => 'Un dépôt manuel n’a pas de connexion à tester.', 'details' => []],
            };
        } catch (TransientIntegrationFailure|PermanentIntegrationFailure $failure) {
            return ['ok' => false, 'message' => $failure->getMessage(), 'details' => ['retryable' => $failure instanceof TransientIntegrationFailure]];
        } catch (RuntimeException $exception) {
            return ['ok' => false, 'message' => $exception->getMessage(), 'details' => []];
        }
    }

    /**
     * @return array{ok: bool, message: string, details: array<string, mixed>}
     */
    private function rest(CustomerImportConfiguration $configuration): array
    {
        $api = $this->rest->find($configuration, false)
            ?? throw new PermanentIntegrationFailure('Aucune connexion REST n’est configurée.');

        $probe = $this->pull->probe($api);

        return ['ok' => true, 'message' => "Le système client a répondu {$probe['httpStatus']}.", 'details' => $probe];
    }

    /**
     * @return array{ok: bool, message: string, details: array<string, mixed>}
     */
    private function files(CustomerImportConfiguration $configuration): array
    {
        $connection = $this->files->for($configuration);
        $count = count($this->remote->candidates($this->remote->open($connection), $connection));

        return ['ok' => true, 'message' => "Connexion établie : {$count} fichier(s) en attente.", 'details' => ['candidates' => $count]];
    }
}
