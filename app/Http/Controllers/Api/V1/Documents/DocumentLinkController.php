<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Documents\StoreDocumentLinkRequest;
use App\Http\Resources\Api\V1\Documents\DocumentLinkResource;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentLink;
use App\Shared\Database\EntityLinkResolver;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Liaisons entre un document et les entités métier (`DocumentLink`).
 *
 * Un même document peut justifier plusieurs entités — un CMR rattaché à la fois
 * au client et à son site, par exemple.
 */
class DocumentLinkController extends Controller
{
    public function __construct(private readonly EntityLinkResolver $entityLinks) {}

    /**
     * Lister les entités rattachées à un document.
     *
     * Permission requise : `documents.view`.
     */
    public function index(Request $request, Document $document): JsonResponse
    {
        $this->requireOrganizationId();
        $this->authorize('view', $document);

        return ApiResponse::ok(DocumentLinkResource::collection($document->links()->get()));
    }

    /**
     * Rattacher un document à une entité de l'organisation active.
     *
     * Permission requise : `documents.upload`. L'entité cible doit appartenir à
     * l'organisation active, sinon 403. Une liaison déjà présente renvoie 409.
     */
    public function store(StoreDocumentLinkRequest $request, Document $document): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [Document::class, $organizationId]);
        $this->authorize('view', $document);

        $data = $request->validated();
        $target = $this->entityLinks->resolveModel($data['entityType'], $data['entityId'], $organizationId);

        $exists = DocumentLink::where('document_id', $document->id)
            ->where('entity_type', $data['entityType'])
            ->where('entity_id', $target->getKey())
            ->exists();

        if ($exists) {
            return ApiResponse::error('Ce document est déjà rattaché à cette entité.', 409);
        }

        $link = $document->links()->create([
            'entity_type' => $data['entityType'],
            'entity_id' => (string) $target->getKey(),
        ]);
        $this->audit($request, $organizationId, 'document_linked', $link, null, $link->toArray());

        return ApiResponse::created(new DocumentLinkResource($link));
    }

    /**
     * Détacher un document d'une entité.
     *
     * Permission requise : `documents.delete`. Une liaison appartenant à un
     * autre document renvoie 404.
     *
     * @response 204
     */
    public function destroy(Request $request, Document $document, DocumentLink $link): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('delete', $document);

        if ($link->document_id !== $document->id) {
            return ApiResponse::error('Liaison introuvable pour ce document.', 404);
        }

        $this->audit($request, $organizationId, 'document_unlinked', $link, $link->toArray(), null);
        $link->delete();

        return ApiResponse::noContent();
    }
}
