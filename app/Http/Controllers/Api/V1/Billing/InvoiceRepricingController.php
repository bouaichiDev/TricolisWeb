<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Api\V1\Concerns\ResolvesInvoiceScope;
use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\RepriceInvoiceAction;
use App\Modules\Billing\Models\Invoice;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recalculer les prix d'une facture au brouillon.
 *
 * Deux verbes pour deux gestes : `GET` montre l'écart, `POST` l'applique. Le
 * §169AM veut que les différences se voient avant d'être écrites — une facture
 * qui bouge en silence ne se contrôle plus.
 *
 * Une facture clôturée est refusée en 422 : ses prix sont historiques (§169AN).
 */
class InvoiceRepricingController extends Controller
{
    use BuildsAuditContext;
    use ResolvesInvoiceScope;

    /**
     * Ce que le recalcul changerait.
     *
     * Permission requise : `invoices.update`. Rien n'est écrit, pas même un
     * historique de calcul.
     */
    public function show(Request $request, Invoice $invoice, RepriceInvoiceAction $action): JsonResponse
    {
        $organizationId = $this->guardInvoice($invoice);
        $this->guardOpenInvoice($invoice);
        $this->authorize('update', $invoice);

        return ApiResponse::ok([
            'changes' => $action->execute($invoice, $this->auditContext($request, $organizationId), apply: false),
        ]);
    }

    /**
     * Appliquer le recalcul.
     *
     * Permission requise : `invoices.update`. Les lignes dont le tarif a
     * disparu gardent leur prix : le §169BO refuse qu'un échec de calcul
     * devienne un montant.
     */
    public function store(Request $request, Invoice $invoice, RepriceInvoiceAction $action): JsonResponse
    {
        $organizationId = $this->guardInvoice($invoice);
        $this->guardOpenInvoice($invoice);
        $this->authorize('update', $invoice);

        return ApiResponse::ok([
            'changes' => $action->execute($invoice, $this->auditContext($request, $organizationId), apply: true),
        ]);
    }
}
