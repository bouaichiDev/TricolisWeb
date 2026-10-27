<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Jobs;

use App\Modules\CustomerPortal\Services\Integrations\CustomerImportRuns;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\DTOs\ImportRun;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Import\CustomerImportHistory;
use App\Modules\Integrations\Services\Import\ImportPipeline;
use App\Modules\Integrations\Services\Import\ImportRunAudit;
use App\Modules\Integrations\Services\ImportSourceReader;
use App\Modules\Integrations\Services\Mapping\MappingDirectives;
use App\Modules\Integrations\Services\Uploads\ImportUploadStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Un gros fichier déposé dans le portail, importé en file.
 *
 * Le résumé est écrit dans le cache du jeton (`ImportUploadStore::putResult`)
 * pour une durée limitée : sans table d'historique (§3), c'est ce que le
 * client peut relire. **Une seule tentative** : l'import est idempotent, mais
 * rejouer en boucle un fichier défectueux n'apprendrait rien de plus.
 */
class ProcessUploadedImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public readonly string $token,
        public readonly string $configurationId,
        public readonly string $customerId,
        public readonly string $userId,
    ) {}

    public function handle(ImportUploadStore $uploads, ImportSourceReader $reader, ImportPipeline $pipeline, CustomerImportRuns $runs, ImportRunAudit $audit, CustomerImportHistory $history): void
    {
        $configuration = CustomerImportConfiguration::with('customer')->where('customer_id', $this->customerId)->find($this->configurationId);
        $entry = $uploads->find($this->token, $this->customerId, $this->configurationId);
        $user = User::find($this->userId);

        if ($configuration === null || $entry === null || $user === null || CustomerImportRuns::refusal($configuration) !== null) {
            $uploads->putResult($this->token, $this->customerId, ['status' => 'failed', 'message' => 'Le fichier ou l’intégration n’est plus disponible : déposez-le à nouveau.']);

            return;
        }

        $uploads->putResult($this->token, $this->customerId, ['status' => 'running', 'fileName' => $entry['fileName']]);

        $startedAt = now();

        try {
            $rows = $reader->read($uploads->contents($entry), (string) $configuration->file_format, MappingDirectives::recordsPath($configuration->mapping));
            $run = $runs->for($configuration, $user);
            $summary = $pipeline->run($run, $rows);
            $audit->record($run, $summary, ['file' => $entry['fileName'], 'jobId' => $this->job?->uuid()], $startedAt);
            $uploads->putResult($this->token, $this->customerId, ['status' => 'completed', 'fileName' => $entry['fileName'], ...$summary->toArray()]);
        } catch (Throwable $exception) {
            report($exception);
            Log::error('customer-integration.upload.failed', ['customerId' => $this->customerId, 'configurationId' => $this->configurationId, 'jobId' => $this->job?->uuid()]);
            // Le message technique reste au journal : l'historique, lu par le client, n'en garde que le fait.
            $history->recordFailure($configuration, ImportRun::PORTAL, 'Le fichier n’a pas pu être importé (fichier illisible ou erreur technique).', $user->id, ['file' => $entry['fileName'], 'jobId' => $this->job?->uuid()]);
            $uploads->putResult($this->token, $this->customerId, ['status' => 'failed', 'message' => 'L’import n’a pas pu aboutir : réessayez ou contactez votre transporteur.']);
        } finally {
            $uploads->forget($this->token, $entry);
        }
    }
}
