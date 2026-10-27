<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services\Content;

use App\Modules\Billing\Actions\RenderInvoiceAction;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\InvoiceClosure;
use App\Modules\Exports\DTOs\InvoiceExportData;
use App\Modules\Exports\Enums\ExportFormat;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Exports\Services\ExportFileName;
use App\Modules\Exports\Services\Formats\InvoiceCsvFormatter;
use App\Modules\Exports\Services\Formats\InvoiceFormatter;
use App\Modules\Exports\Services\Formats\InvoiceJsonFormatter;
use App\Modules\Exports\Services\Formats\InvoicePdfFormatter;
use App\Modules\Exports\Services\Formats\InvoiceXmlFormatter;
use App\Shared\Database\MorphMap;
use RuntimeException;

/**
 * Le fichier d'une facture exportée — le contenu historique du moteur
 * d'export, déplacé tel quel hors du dispatcher.
 *
 * **La clôture est revérifiée ici** : même si quelqu'un fabriquait un
 * `ExportJob` à la main, une facture non clôturée ne part pas. Les trois
 * clients (job, configuration, facture) doivent concorder.
 */
final readonly class InvoiceExportContent implements ExportContentBuilder
{
    public function __construct(
        private InvoiceClosure $closure,
        private InvoiceJsonFormatter $json,
        private InvoiceXmlFormatter $xml,
        private InvoiceCsvFormatter $csv,
        private InvoicePdfFormatter $pdf,
        private RenderInvoiceAction $render,
        private ExportFileName $names,
    ) {}

    public function build(ExportJob $job, CustomerExportConfiguration $configuration): ExportContent
    {
        $invoice = $this->invoiceOf($job);

        if ($configuration->customer_id !== $invoice->customer_id || $job->customer_id !== $invoice->customer_id) {
            throw new RuntimeException('Cette destination n’appartient pas au client de la facture.');
        }

        $settings = $configuration->settings ?? [];

        if ($configuration->format === ExportFormat::PDF) {
            return new ExportContent(
                $this->pdf->fromDocument($this->render->execute($invoice), $settings),
                $this->names->build($configuration, $invoice, $this->pdf->extension()),
                $this->pdf->contentType(),
            );
        }

        $formatter = $this->formatterFor($configuration->format);

        return new ExportContent(
            $formatter->render(
                InvoiceExportData::from($invoice->load(['lines.addressSnapshot'])),
                $settings,
                (string) ($configuration->encoding ?? 'UTF-8'),
            ),
            $this->names->build($configuration, $invoice, $formatter->extension()),
            $formatter->contentType(),
        );
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

    /**
     * Les formats qui transposent le DTO. Le PDF n'en est pas : il se rend
     * depuis un modèle.
     */
    private function formatterFor(ExportFormat $format): InvoiceFormatter
    {
        return match ($format) {
            ExportFormat::JSON => $this->json,
            ExportFormat::XML => $this->xml,
            ExportFormat::CSV => $this->csv,
            ExportFormat::PDF => throw new RuntimeException('Le PDF se rend depuis un modèle, pas depuis un mapping.'),
        };
    }
}
