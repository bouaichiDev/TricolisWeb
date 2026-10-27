<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Exports\Enums\ExportTransport;
use App\Modules\Exports\Exceptions\TransientExportFailure;
use App\Modules\Exports\Jobs\ProcessExportJob;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Exports\Services\Content\ExportContentFactory;
use App\Modules\Exports\Services\Transports\EmailExportTransporter;
use App\Modules\Exports\Services\Transports\ExportTransporter;
use App\Modules\Exports\Services\Transports\FileExportTransporter;
use App\Modules\Exports\Services\Transports\ManualExportTransporter;
use App\Modules\Exports\Services\Transports\RestApiExportTransporter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Génère, stocke et transmet un export ; consigne ce qui s'est passé.
 *
 * Le contenu dépend de l'entité (`ExportContentFactory` : facture clôturée,
 * statut de commande), le transport de la configuration. Chaque tentative
 * avance `attemptCount` et date `generatedAt` ; un succès date `sentAt`.
 *
 * **Un échec ne remonte pas** : il s'écrit sur le job — statut, message,
 * tentatives — d'où l'utilisateur peut le reprendre. Si la configuration
 * l'autorise (`ExportRetryPolicy`) et que l'échec est passager, une nouvelle
 * tentative est mise en file, différée.
 */
final readonly class ExportDispatcher
{
    private const string DISK = 'local';

    public function __construct(
        private ExportContentFactory $contents,
        private ExportRetryPolicy $retries,
        private RestApiExportTransporter $rest,
        private FileExportTransporter $files,
        private EmailExportTransporter $email,
        private ManualExportTransporter $manual,
        private WriteAuditLog $audit,
    ) {}

    public function process(ExportJob $job): void
    {
        $job->forceFill(['status' => 'processing'])->save();

        try {
            $this->transmit($job);
        } catch (Throwable $exception) {
            $this->fail($job, $exception);
        }
    }

    private function transmit(ExportJob $job): void
    {
        $configuration = $job->configuration;

        if ($configuration === null || ! $configuration->is_active) {
            throw new RuntimeException('La configuration d’export est absente ou désactivée.');
        }

        $content = $this->contents->for($job->entity_type)->build($job, $configuration);
        $path = sprintf('exports/%s/%s', $job->id, $content->fileName);

        Storage::disk(self::DISK)->put($path, $content->contents);

        $job->forceFill([
            'file_name' => $content->fileName,
            'storage_path' => $path,
            'generated_at' => now(),
            'attempt_count' => $job->attempt_count + 1,
        ])->save();

        $this->transporterFor($configuration->transport)
            ->send($configuration, $content->fileName, $content->contents, $content->contentType);

        $job->forceFill(['status' => 'sent', 'sent_at' => now(), 'error_message' => null])->save();

        $this->write($job, 'export_job.sent');
    }

    private function transporterFor(ExportTransport $transport): ExportTransporter
    {
        return match ($transport) {
            ExportTransport::REST_API => $this->rest,
            ExportTransport::FTP, ExportTransport::SFTP => $this->files,
            ExportTransport::EMAIL => $this->email,
            ExportTransport::MANUAL => $this->manual,
        };
    }

    private function fail(ExportJob $job, Throwable $exception): void
    {
        $job->forceFill([
            'status' => 'failed',
            'attempt_count' => max($job->attempt_count, 1),
            'error_message' => mb_substr($exception->getMessage(), 0, 1000),
        ])->save();

        $this->write($job, 'export_job.failed');

        $configuration = $job->configuration;

        if ($exception instanceof TransientExportFailure && $configuration !== null
            && $job->attempt_count < $this->retries->maxAttempts($configuration)) {
            $delay = $this->retries->delayAfter($configuration, $job->attempt_count);

            Log::warning('export-job.retry_scheduled', [
                'exportJobId' => $job->id,
                'customerId' => $job->customer_id,
                'configurationId' => $job->configuration_id,
                'attemptCount' => $job->attempt_count,
                'delaySeconds' => $delay,
            ]);

            ProcessExportJob::dispatch($job->id)->delay(now()->addSeconds($delay));
        }
    }

    private function write(ExportJob $job, string $action): void
    {
        $organizationId = $job->customer?->organization_id;

        if ($organizationId === null) {
            return;
        }

        $this->audit->execute(
            $organizationId,
            null,
            $action,
            $job,
            null,
            ['status' => $job->status, 'attemptCount' => $job->attempt_count],
            null,
            null,
        );
    }
}
