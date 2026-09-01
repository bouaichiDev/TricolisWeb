<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\DTOs\RenderedInvoice;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\InvoiceRenderContext;
use App\Modules\Exports\DTOs\InvoiceExportData;
use App\Modules\Templates\Actions\ResolveTemplateAction;
use App\Modules\Templates\DTOs\TemplateQuery;
use App\Modules\Templates\Services\TemplateRenderer;
use Illuminate\Support\Facades\View;

/**
 * Le document d'une facture, en HTML.
 *
 * Trois chemins, dans cet ordre :
 *
 * 1. **facture close** — le document figé à la clôture est rendu tel quel. Le
 *    §0.22 l'exige : modifier le modèle en septembre ne réécrit pas les
 *    factures d'août, et deux relectures de la même facture montrent la même
 *    chose ;
 * 2. **modèle résolu** — le modèle du client, sinon celui de l'organisation ;
 * 3. **aucun modèle** — la mise en page livrée en Phase 8.
 *
 * Le troisième chemin n'est pas un contournement du §0.9. Ce paragraphe
 * interdit de servir le modèle **d'un autre client** ; il ne dit rien du cas où
 * l'organisation n'en a configuré aucun — le cas de toutes les organisations
 * existantes au jour de la migration. Retomber sur la mise en page livrée fait
 * qu'une facture continue de se produire sans configuration préalable ; refuser
 * aurait cassé la facturation de tout le monde en une migration.
 */
final readonly class RenderInvoiceAction
{
    /** Mise en page employée quand l'organisation n'a défini aucun modèle. */
    private const string FALLBACK_VIEW = 'exports.invoice';

    public function __construct(
        private ResolveTemplateAction $resolve,
        private TemplateRenderer $renderer,
        private InvoiceRenderContext $context,
    ) {}

    public function execute(Invoice $invoice): RenderedInvoice
    {
        if ($invoice->rendered_body !== null) {
            return RenderedInvoice::frozen($invoice->rendered_body, $invoice->template_id);
        }

        return $this->renderFresh($invoice);
    }

    /**
     * Rend depuis le modèle courant, sans tenir compte d'un éventuel cliché.
     *
     * C'est ce que l'aperçu d'un brouillon doit montrer — l'état du modèle
     * maintenant — et ce que la clôture fige.
     */
    public function renderFresh(Invoice $invoice): RenderedInvoice
    {
        $template = $this->resolve->execute(
            TemplateQuery::forInvoice($invoice->organization_id, $invoice->customer_id),
        );

        if ($template === null) {
            return RenderedInvoice::fallback($this->fallbackHtml($invoice));
        }

        $rendered = $this->renderer->renderDocument($template, $this->context->build($invoice));

        return RenderedInvoice::fromTemplate($rendered->body, $template);
    }

    private function fallbackHtml(Invoice $invoice): string
    {
        return View::make(self::FALLBACK_VIEW, [
            'invoice' => InvoiceExportData::from($invoice->loadMissing(['lines.addressSnapshot'])),
            'title' => 'Facture',
            'footnotes' => [],
        ])->render();
    }
}
