<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Api\V1\Concerns\ResolvesInvoiceScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Billing\InvoiceDetailResource;
use App\Http\Resources\Api\V1\Exports\ExportJobResource;
use App\Modules\Billing\Actions\CloseInvoiceAction;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\InvoiceClosure;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Clôturer une facture.
 *
 * Séparé du CRUD : clôturer n'est pas modifier. C'est un geste métier qui fige
 * le document et déclenche son envoi chez le client, et le §111 demande qu'il
 * porte sa propre permission.
 *
 * Le §24 interdit un bouton générique `/send` : l'envoi n'est pas une action, il
 * est la conséquence de la clôture.
 */
class InvoiceClosureController extends Controller
{
    use BuildsAuditContext;
    use ResolvesInvoiceScope;

    /**
     * Clôturer une facture et mettre ses envois en file.
     *
     * Permission requise : `invoices.close`.
     *
     * Rejouable : une facture déjà clôturée rend son état sans recréer d'envoi
     * (§30). Une facture sans ligne, ou dont l'état n'autorise pas le passage,
     * est refusée en 422.
     */
    public function store(Request $request, Invoice $invoice, CloseInvoiceAction $action): JsonResponse
    {
        $organizationId = $this->guardInvoice($invoice);
        $this->authorize('close', $invoice);

        $jobs = $action->execute($invoice, $this->auditContext($request, $organizationId));

        return ApiResponse::ok([
            'invoice' => new InvoiceDetailResource(
                $invoice->fresh()->load(['customer', 'lines.addressSnapshot'])->loadCount('lines'),
            ),
            // Ce qui part, et où. Zéro destination est un cas normal — le §28
            // refuse d'en faire un blocage.
            'exportJobs' => ExportJobResource::collection($jobs),
        ]);
    }

    /**
     * Ce que la clôture déclenchera, avant de la confirmer.
     *
     * Permission requise : `invoices.view`. Le §52 veut que l'utilisateur voie
     * ses destinations — ou apprenne qu'il n'y en a aucune — avant de figer un
     * document qu'il ne pourra plus rouvrir.
     */
    public function show(Invoice $invoice, CloseInvoiceAction $action, InvoiceClosure $closure): JsonResponse
    {
        $this->guardInvoice($invoice);
        $this->authorize('view', $invoice);

        $configurations = $action->destinationsFor($invoice);

        return ApiResponse::ok([
            'closable' => $closure->isClosable($invoice),
            'lineCount' => $invoice->lines()->count(),
            'destinations' => array_map(static fn ($configuration): array => [
                'id' => $configuration->id,
                'name' => $configuration->name,
                'format' => $configuration->format->value,
                'transport' => $configuration->transport->value,
            ], $configurations),
        ]);
    }
}
