<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Claims;

use App\Http\Controllers\Api\V1\Customer\Concerns\ResolvesCustomerClaim;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Claims\StoreCustomerClaimDocumentRequest;
use App\Modules\CustomerPortal\Exceptions\CustomerClaimException;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\CustomerPortal\Services\Documents\CustomerDocumentAccess;
use App\Modules\CustomerPortal\Services\Documents\CustomerDocumentFiles;
use App\Modules\Documents\Actions\AttachEntityDocument;
use App\Modules\Documents\Models\Document;
use App\Shared\Database\MorphMap;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Pièces jointes d'une réclamation du client (§23).
 *
 * `Claim` figure déjà dans la morph map et l'interne y lie ses pièces par
 * `DocumentLink` (type `claim_evidence`) : le portail reprend exactement ce
 * lien et ce type — aucune table nouvelle. Le téléchargement passe par
 * `GET /documents/{id}/download`, qui revérifie le chemin jusqu'au client.
 */
class CustomerClaimDocumentController extends Controller
{
    use ResolvesCustomerClaim;

    /** Le type que l'interne donne aux pièces d'une réclamation (`ClaimAttachments`). */
    private const string DOCUMENT_TYPE = 'claim_evidence';

    public function __construct(private readonly CurrentCustomerContext $context) {}

    /** Permission : `customer.claims.view`. */
    public function index(string $claim, CustomerDocumentAccess $access): JsonResponse
    {
        $resolved = $this->claim($this->context, $claim);
        $documents = $access->linkedTo(MorphMap::CLAIM, $resolved->id)->latest()->get();

        return ApiResponse::ok($documents->map(static fn (Document $document): array => CustomerDocumentFiles::present($document))->all());
    }

    /** Permission : `customer.claims.create`. Réclamation ouverte seulement. */
    public function store(StoreCustomerClaimDocumentRequest $request, string $claim, AttachEntityDocument $attach, CustomerPortalAudit $audit): JsonResponse
    {
        $resolved = $this->claim($this->context, $claim);

        if ($resolved->isClosed()) {
            throw CustomerClaimException::notEditable();
        }

        $document = $attach->execute($resolved, $resolved->organization_id, $request->file('file'), [
            'documentType' => self::DOCUMENT_TYPE,
            'status' => 'active',
            'receivedAt' => now()->toDateTimeString(),
        ], $this->context->user(), $request->ip());

        $audit->record($this->context->customer(), $this->context->user(), 'claim_document_uploaded', $resolved, null, [
            'claimId' => $resolved->id,
            'documentId' => $document->id,
        ], $request->ip());

        return ApiResponse::created(CustomerDocumentFiles::present($document));
    }
}
