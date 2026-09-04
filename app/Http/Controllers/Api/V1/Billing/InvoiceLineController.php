<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Api\V1\Concerns\ResolvesInvoiceScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Billing\StoreInvoiceLineRequest;
use App\Http\Requests\Api\V1\Billing\UpdateInvoiceLineRequest;
use App\Http\Resources\Api\V1\Billing\InvoiceLineResource;
use App\Modules\Billing\Actions\AddInvoiceLineAction;
use App\Modules\Billing\Actions\RemoveInvoiceLineAction;
use App\Modules\Billing\Actions\UpdateInvoiceLineAction;
use App\Modules\Billing\DTOs\CreateInvoiceLineData;
use App\Modules\Billing\DTOs\UpdateInvoiceLineData;
use App\Modules\Billing\Exceptions\InvoiceLineRequired;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lignes d'une facture.
 */
class InvoiceLineController extends Controller
{
    use BuildsAuditContext;
    use ResolvesInvoiceScope;

    /**
     * Lister les lignes d'une facture, par numéro croissant.
     *
     * Permission requise : `invoice_lines.view`.
     */
    public function index(ListRequest $request, Invoice $invoice): JsonResponse
    {
        $organizationId = $this->guardInvoice($invoice);
        $this->authorize('viewAny', [InvoiceLine::class, $organizationId]);

        $paginator = $invoice->lines()
            ->with('addressSnapshot')
            ->orderBy('line_number')
            ->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (InvoiceLine $l) => new InvoiceLineResource($l)));
    }

    /**
     * Ajouter une ligne.
     *
     * Permission requise : `invoice_lines.create`. Les totaux de la ligne et de
     * la facture sont recalculés.
     */
    public function store(StoreInvoiceLineRequest $request, Invoice $invoice, AddInvoiceLineAction $action): JsonResponse
    {
        $organizationId = $this->guardInvoice($invoice);
        $this->guardOpenInvoice($invoice);
        $this->authorize('create', [InvoiceLine::class, $organizationId]);

        $line = $action->execute(
            $invoice,
            CreateInvoiceLineData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new InvoiceLineResource($line->load('addressSnapshot')));
    }

    /**
     * Consulter une ligne.
     *
     * Permission requise : `invoice_lines.view`.
     */
    public function show(Request $request, Invoice $invoice, InvoiceLine $line): JsonResponse
    {
        $this->guardLine($invoice, $line);
        $this->authorize('view', $line);

        return ApiResponse::ok(new InvoiceLineResource($line->load('addressSnapshot')));
    }

    /**
     * Modifier une ligne.
     *
     * Permission requise : `invoice_lines.update`. Toute modification de la
     * quantité, du prix ou d'un taux recalcule les totaux.
     */
    public function update(UpdateInvoiceLineRequest $request, Invoice $invoice, InvoiceLine $line, UpdateInvoiceLineAction $action): JsonResponse
    {
        $organizationId = $this->guardLine($invoice, $line);
        $this->guardOpenInvoice($invoice);
        $this->authorize('update', $line);

        $updated = $action->execute(
            $line,
            UpdateInvoiceLineData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new InvoiceLineResource($updated));
    }

    /**
     * Retirer une ligne.
     *
     * Permission requise : `invoice_lines.delete`. Refusé en 409 si c'est la
     * dernière : une facture doit conserver au moins une ligne.
     *
     * @response 204
     */
    public function destroy(Request $request, Invoice $invoice, InvoiceLine $line, RemoveInvoiceLineAction $action): JsonResponse
    {
        $organizationId = $this->guardLine($invoice, $line);
        // Une facture cloturee est figee, lignes comprises : le §22 l'enumere.
        $this->guardOpenInvoice($invoice);
        $this->authorize('delete', $line);

        try {
            $action->execute($line, $this->auditContext($request, $organizationId));
        } catch (InvoiceLineRequired $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::noContent();
    }

    private function guardLine(Invoice $invoice, InvoiceLine $line): string
    {
        $organizationId = $this->guardInvoice($invoice);
        abort_unless($line->invoice_id === $invoice->id, 404, 'Ligne introuvable.');

        return $organizationId;
    }
}
