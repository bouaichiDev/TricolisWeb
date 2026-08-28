<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\InvoiceClosure;

/**
 * Périmètre et immutabilité d'une facture.
 *
 * `guardInvoice` répond **404** pour une facture d'une autre organisation : un
 * 403 confirmerait que cet identifiant existe ailleurs, et c'est la différence
 * entre les deux réponses qui constitue la fuite.
 *
 * `guardOpenInvoice` refuse toute écriture sur une facture clôturée. Le §22
 * l'exige, et le précise : *« ne pas se contenter de désactiver les boutons
 * React »*. Une facture transmise à un système externe doit rester
 * historiquement stable — sinon le client détient un document que la base
 * contredit.
 */
trait ResolvesInvoiceScope
{
    /** @return string l'organisation active */
    protected function guardInvoice(Invoice $invoice): string
    {
        $organizationId = $this->requireOrganizationId();

        abort_unless($invoice->organization_id === $organizationId, 404, 'Facture introuvable.');

        return $organizationId;
    }

    /**
     * Refuse d'écrire sur une facture clôturée.
     *
     * **422 et non 403** : ce n'est pas une question de droit mais d'état. Le
     * même utilisateur, avec les mêmes permissions, pouvait le faire une minute
     * plus tôt.
     */
    protected function guardOpenInvoice(Invoice $invoice): void
    {
        abort_if(
            app(InvoiceClosure::class)->isClosed($invoice),
            422,
            'Cette facture est clôturée : elle ne peut plus être modifiée.',
        );
    }
}
