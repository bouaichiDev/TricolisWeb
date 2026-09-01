<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\InvoiceClosure;
use App\Modules\Exports\DTOs\InvoiceExportData;
use App\Modules\Exports\Enums\ExportFormat;
use App\Modules\Exports\Enums\ExportTransport;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Exports\Services\Formats\InvoiceCsvFormatter;
use App\Modules\Exports\Services\Formats\InvoiceFormatter;
use App\Modules\Exports\Services\Formats\InvoiceJsonFormatter;
use App\Modules\Exports\Services\Formats\InvoicePdfFormatter;
use App\Modules\Exports\Services\Formats\InvoiceXmlFormatter;
use App\Modules\Exports\Services\Transports\EmailExportTransporter;
use App\Modules\Exports\Services\Transports\ExportTransporter;
use App\Modules\Exports\Services\Transports\FileExportTransporter;
use App\Modules\Exports\Services\Transports\ManualExportTransporter;
use App\Modules\Exports\Services\Transports\RestApiExportTransporter;
use App\Shared\Database\MorphMap;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Génère, stocke et transmet un export ; consigne ce qui s'est passé.
 *
 * **La clôture est revérifiée ici.** Le §88 l'exige : même si quelqu'un
 * fabriquait un `ExportJob` à la main par l'API, une facture non clôturée ne
 * part pas. La sécurité métier ne repose pas sur le seul chemin nominal.
 *
 * **Un échec ne remonte pas.** Le §27 le dit : une API en panne ne rouvre pas la
 * facture, et ne doit pas faire échouer le travail en file au point de le
 * rejouer indéfiniment. L'échec s'écrit sur le job — statut, message, nombre de
 * tentatives — d'où l'utilisateur peut le reprendre.
 */
final readonly class ExportDispatcher
{
    private const string DISK = 'local';

    public function __construct(
        private InvoiceClosure $closure,
        private InvoiceJsonFormatter $json,
        private InvoiceXmlFormatter $xml,
        private InvoiceCsvFormatter $csv,
        private InvoicePdfFormatter $pdf,
        private RestApiExportTransporter $rest,
        private FileExportTransporter $files,
        private EmailExportTransporter $email,
        private ManualExportTransporter $manual,
        private ExportFileName $names,
        private WriteAuditLog $audit,
    ) {}

    public function process(ExportJob $job): void
    {
        $job->forceFill(['status' => 'processing'])->save();

        try {
            $this->transmit($job);
        } catch (Throwable $exception) {
            $this->fail($job, $exception->getMessage());

            return;
        }
    }

    private function transmit(ExportJob $job): void
    {
        $configuration = $job->configuration;

        if ($configuration === null || ! $configuration->is_active) {
            throw new RuntimeException('La configuration d’export est absente ou désactivée.');
        }

        $invoice = $this->invoiceOf($job);

        // Le §114 : les trois clients doivent concorder. Une configuration d'un
        // autre client ne doit jamais recevoir cette facture, meme si le job a
        // ete forge.
        if ($configuration->customer_id !== $invoice->customer_id || $job->customer_id !== $invoice->customer_id) {
            throw new RuntimeException('Cette destination n’appartient pas au client de la facture.');
        }

        $formatter = $this->formatterFor($configuration->format);
        $encoding = (string) ($configuration->encoding ?? 'UTF-8');

        $contents = $formatter->render(
            InvoiceExportData::from($invoice->load(['lines.addressSnapshot'])),
            $configuration->settings ?? [],
            $encoding,
        );

        $fileName = $this->names->build($configuration, $invoice, $formatter->extension());
        $path = sprintf('exports/%s/%s', $job->id, $fileName);

        Storage::disk(self::DISK)->put($path, $contents);

        $job->forceFill([
            'file_name' => $fileName,
            'storage_path' => $path,
            'generated_at' => now(),
            'attempt_count' => $job->attempt_count + 1,
        ])->save();

        $this->transporterFor($configuration->transport)
            ->send($configuration, $fileName, $contents, $formatter->contentType());

        $job->forceFill(['status' => 'sent', 'sent_at' => now(), 'error_message' => null])->save();

        $this->write($job, 'export_job.sent');
    }

    /** La facture visée, si elle existe et si elle est bien clôturée. */
    private function invoiceOf(ExportJob $job): Invoice
    {
        if ($job->entity_type !== MorphMap::INVOICE || $job->entity_id === null) {
            throw new RuntimeException('Cet envoi ne porte pas sur une facture.');
        }

        $invoice = Invoice::find($job->entity_id);

        if ($invoice === null) {
            throw new RuntimeException('La facture visée n’existe plus.');
        }

        if (! $this->closure->isClosed($invoice)) {
            throw new RuntimeException('Une facture non clôturée ne peut pas être transmise.');
        }

        return $invoice;
    }

    private function formatterFor(ExportFormat $format): InvoiceFormatter
    {
        return match ($format) {
            ExportFormat::JSON => $this->json,
            ExportFormat::XML => $this->xml,
            ExportFormat::CSV => $this->csv,
            ExportFormat::PDF => $this->pdf,
        };
    }

    private function transporterFor(ExportTransport $transport): ExportTransporter
    {
        return match ($transport) {
            ExportTransport::REST_API => $this->rest,
            ExportTransport::FTP, ExportTransport::SFTP => $this->files,
            ExportTransport::EMAIL => $this->email,
            // Manuel : le fichier est produit et range, personne n'est appele.
            ExportTransport::MANUAL => $this->manual,
        };
    }

    private function fail(ExportJob $job, string $message): void
    {
        $job->forceFill([
            'status' => 'failed',
            // Le compteur n'avance que si l'envoi a ete tente : une
            // configuration absente n'est pas une tentative.
            'attempt_count' => max($job->attempt_count, 1),
            'error_message' => mb_substr($message, 0, 1000),
        ])->save();

        $this->write($job, 'export_job.failed');
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
