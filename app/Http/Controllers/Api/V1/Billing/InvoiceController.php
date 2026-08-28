<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Api\V1\Concerns\ResolvesInvoiceScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Billing\ListInvoiceRequest;
use App\Http\Requests\Api\V1\Billing\StoreInvoiceRequest;
use App\Http\Requests\Api\V1\Billing\UpdateInvoiceRequest;
use App\Http\Resources\Api\V1\Billing\InvoiceDetailResource;
use App\Http\Resources\Api\V1\Billing\InvoiceListResource;
use App\Modules\Billing\Actions\CreateInvoiceAction;
use App\Modules\Billing\Actions\DeleteInvoiceAction;
use App\Modules\Billing\Actions\UpdateInvoiceAction;
use App\Modules\Billing\DTOs\CreateInvoiceData;
use App\Modules\Billing\DTOs\UpdateInvoiceData;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Queries\InvoiceListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Factures clients.
 */
class InvoiceController extends Controller
{
    use BuildsAuditContext;
    use ResolvesInvoiceScope;

    /**
     * Lister les factures.
     *
     * Permission requise : `invoices.view`. Recherche sur `invoice_number`,
     * `external_reference` et `remark`.
     */
    public function index(ListInvoiceRequest $request, InvoiceListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [Invoice::class, $organizationId]);

        $paginator = $query->paginate($request, $organizationId);

        return ApiResponse::paginated($paginator->through(fn (Invoice $i) => new InvoiceListResource($i)));
    }

    /**
     * Créer une facture avec ses lignes.
     *
     * Permission requise : `invoices.create`. Au moins une ligne est exigée, et
     * les totaux sont calculés — les fournir n'a aucun effet.
     */
    public function store(StoreInvoiceRequest $request, CreateInvoiceAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [Invoice::class, $organizationId]);

        $invoice = $action->execute(
            CreateInvoiceData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
            now()->toDateTimeString(),
        );

        return ApiResponse::created(new InvoiceDetailResource($invoice->load('lines.addressSnapshot')));
    }

    /**
     * Consulter une facture, lignes comprises.
     *
     * Permission requise : `invoices.view`.
     */
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $this->guardInvoice($invoice);
        $this->authorize('view', $invoice);

        return ApiResponse::ok(new InvoiceDetailResource(
            $invoice->load(['customer', 'lines.addressSnapshot'])->loadCount('lines'),
        ));
    }

    /**
     * Modifier l'en-tête d'une facture.
     *
     * Permission requise : `invoices.update`. Ni le client ni les totaux ne sont
     * modifiables, et une facture clôturée ne l'est plus du tout.
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice, UpdateInvoiceAction $action): JsonResponse
    {
        $organizationId = $this->guardInvoice($invoice);
        $this->guardOpenInvoice($invoice);
        $this->authorize('update', $invoice);

        $updated = $action->execute(
            $invoice,
            UpdateInvoiceData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new InvoiceDetailResource($updated));
    }

    /**
     * Supprimer une facture et ses lignes.
     *
     * Permission requise : `invoices.delete`.
     *
     * @response 204
     */
    public function destroy(Request $request, Invoice $invoice, DeleteInvoiceAction $action): JsonResponse
    {
        $organizationId = $this->guardInvoice($invoice);
        // Le §22 : une facture cloturee ne se supprime pas. Elle est peut-etre
        // deja chez le client.
        $this->guardOpenInvoice($invoice);
        $this->authorize('delete', $invoice);

        $action->execute($invoice, $this->auditContext($request, $organizationId));

        return ApiResponse::noContent();
    }
}
