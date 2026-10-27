<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Pull;

use App\Modules\Identity\Models\User;
use App\Modules\Integrations\DTOs\ImportRun;
use App\Modules\Integrations\DTOs\ImportSummary;
use App\Modules\Integrations\Exceptions\TransientIntegrationFailure;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Import\CustomerImportHistory;
use App\Modules\Integrations\Services\Import\ImportPipeline;
use App\Modules\Integrations\Services\Import\ImportRunAudit;
use App\Modules\Integrations\Services\ImportSourceReader;
use App\Modules\Integrations\Services\Mapping\MappingDirectives;
use App\Modules\Integrations\Services\Sources\InboundFileConnections;
use App\Modules\Integrations\Services\Sources\IntegrationActor;
use App\Modules\Integrations\Services\Sources\RemoteFileSource;
use Closure;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * La relève FTP/SFTP : lister, télécharger, lire, importer, ranger.
 *
 * **Chaque fichier est traité pour lui-même.** Un fichier illisible (XML mal
 * formé, XXE, trop gros) est journalisé et **laissé en place** ; les suivants
 * passent. Un fichier dont une commande est refusée reste aussi sur le
 * serveur : il sera relu, et les commandes déjà créées seront ignorées par
 * l'idempotence. Seul un fichier entièrement importé est archivé ou supprimé.
 */
final readonly class FilePullRunner
{
    public function __construct(
        private InboundFileConnections $connections,
        private RemoteFileSource $source,
        private ImportSourceReader $reader,
        private ImportPipeline $pipeline,
        private IntegrationActor $actors,
        private ImportRunAudit $audit,
        private CustomerImportHistory $history,
    ) {}

    /**
     * @param  Closure(User): ImportRun  $runFor
     */
    public function run(CustomerImportConfiguration $configuration, Closure $runFor, ?string $jobId): ImportSummary
    {
        $connection = $this->connections->for($configuration);
        $run = $runFor($this->actors->resolve($configuration->customer->organization_id, null, $connection->actorEmail));
        $disk = $this->source->open($connection);
        $summary = ImportSummary::empty();

        foreach ($this->source->candidates($disk, $connection) as $path) {
            $file = basename($path);
            $startedAt = now();

            try {
                $rows = $this->reader->read(
                    $this->source->read($disk, $path),
                    (string) $configuration->file_format,
                    MappingDirectives::recordsPath($configuration->mapping),
                );
            } catch (TransientIntegrationFailure $failure) {
                throw $failure;
            } catch (RuntimeException $exception) {
                // Illisible ou refusé (trop gros, XML mal formé, DTD) : un
                // nouvel essai donnerait le même verdict.
                Log::warning('customer-integration.file_rejected', [
                    'customerId' => $configuration->customer_id,
                    'configurationId' => $configuration->id,
                    'file' => $file,
                    'reason' => $exception->getMessage(),
                    'jobId' => $jobId,
                ]);
                $this->history->recordFailure($configuration, ImportRun::PULL, "Fichier écarté : {$exception->getMessage()}", null, ['file' => $file, 'jobId' => $jobId]);

                continue;
            }

            $fileSummary = $this->pipeline->run($run, $rows);
            $fate = $fileSummary->isClean() ? $this->source->finish($disk, $connection, $path) : 'kept';

            $this->audit->record($run, $fileSummary, ['file' => $file, 'fileFate' => $fate, 'jobId' => $jobId], $startedAt);
            $summary = $summary->merge($fileSummary);
        }

        return $summary;
    }
}
