<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\V1\Concerns\ResolvesInvoiceScope;
use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\RenderInvoiceAction;
use App\Modules\Billing\Models\Invoice;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Le document d'une facture.
 *
 * **Un aperçu, pas un envoi.** Le §0.20 le veut sur un brouillon : voir la
 * facture telle qu'elle partira avant de la figer. Le §24 interdit d'en faire
 * un bouton d'envoi déguisé — rien ne quitte le système ici.
 *
 * Le HTML est rendu par le moteur unique, jamais reconstruit côté navigateur :
 * le §0.20 interdit un second moteur en JavaScript, qui montrerait un document
 * différent de celui que le client recevra.
 *
 * `templateName` et `scope` accompagnent le document parce que sans eux
 * l'utilisateur ne saurait pas *pourquoi* il voit cette mise en page — son
 * modèle client a-t-il servi, ou n'a-t-il jamais été créé ?
 */
class InvoiceDocumentController extends Controller
{
    use ResolvesInvoiceScope;

    /**
     * Prévisualiser le document d'une facture.
     *
     * Permission requise : `invoices.view`. Une facture close rend le document
     * figé à sa clôture ; un brouillon se rend depuis le modèle du moment.
     */
    public function show(Invoice $invoice, RenderInvoiceAction $action): JsonResponse
    {
        $this->guardInvoice($invoice);
        $this->authorize('view', $invoice);

        $document = $action->execute($invoice);

        return ApiResponse::ok([
            'html' => $document->html,
            'templateId' => $document->templateId,
            'templateName' => $document->templateName,
            'scope' => $document->scope,
            'isFrozen' => $document->isFrozen,
            'renderedAt' => $invoice->rendered_at?->toIso8601String(),
        ]);
    }
}
