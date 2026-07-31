<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Documents\StoreDocumentRequest;
use App\Http\Resources\Api\V1\Documents\DocumentResource;
use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Documents\Models\Document;
use App\Shared\Database\EntityLinkResolver;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        private readonly WriteAuditLog $audit,
        private readonly EntityLinkResolver $entityLinks,
    ) {}

    /**
     * Lister les documents de l'organisation active.
     *
     * Permission requise : `documents.view`. Les documents supprimés
     * logiquement sont exclus. Recherche sur `reference_number` et `file_name`.
     */
    public function index(ListRequest $request): JsonResponse
    {
        $org = $this->requireOrganizationId();
        $this->authorize('viewAny', [Document::class, $org]);
        $query = Document::where('organization_id', $org)->with('links');
        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($q) => $q->where('reference_number', 'like', "%$search%")->orWhere('file_name', 'like', "%$search%"));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        return ApiResponse::paginated($query->latest()->paginate($request->getPerPage())->through(fn ($document) => new DocumentResource($document)));
    }

    /**
     * Téléverser un document.
     *
     * Permission requise : `documents.upload`. Extensions autorisées : pdf, jpg,
     * jpeg, png, doc, docx, xls, xlsx, csv, txt ; taille maximale 10 Mo. Le type
     * MIME est déduit du contenu, jamais de l'en-tête envoyé par le client, et le
     * chemin de stockage n'est pas exposé.
     */
    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $org = $this->requireOrganizationId();
        $this->authorize('create', [Document::class, $org]);
        $data = $request->validated();
        $entity = $this->resolveEntity($data['entityType'] ?? null, $data['entityId'] ?? null, $org);
        $file = $request->file('file');
        $path = $file->store("documents/$org", 'local');
        try {
            $document = DB::transaction(function () use ($data, $file, $path, $org, $request, $entity): Document {
                $document = Document::create(['organization_id' => $org, 'reference_number' => $data['referenceNumber'] ?? null, 'document_type' => $data['documentType'], 'status' => $data['status'], 'file_name' => $file->getClientOriginalName(), 'storage_path' => $path, 'mime_type' => $file->getMimeType() ?? 'application/octet-stream', 'size' => $file->getSize(), 'received_at' => $data['receivedAt'] ?? null, 'created_by' => $request->user()->id]);
                if ($entity !== null) {
                    $document->links()->create(['entity_type' => $data['entityType'], 'entity_id' => $entity->getKey()]);
                }
                $this->audit->execute($org, $request->user(), 'created', $document, null, $document->toArray(), $request);

                return $document;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return ApiResponse::created(new DocumentResource($document->load('links')));
    }

    /**
     * Consulter les métadonnées d'un document.
     *
     * Permission requise : `documents.view`.
     */
    public function show(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        return ApiResponse::ok(new DocumentResource($document->load('links')));
    }

    /**
     * Télécharger le fichier d'un document.
     *
     * Permission requise : `documents.view`. Le fichier est servi en flux depuis
     * le disque privé ; un fichier absent renvoie 404.
     */
    public function download(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        return Storage::disk('local')->download($document->storage_path, $document->file_name, ['Content-Type' => $document->mime_type]);
    }

    /**
     * Supprimer un document.
     *
     * Permission requise : `documents.delete`. La suppression est logique : le
     * fichier reste stocké pendant la période de rétention configurée
     * (`tricolis.document_retention_days`), puis la commande `documents:purge`
     * le détruit définitivement.
     *
     * @response 204
     */
    public function destroy(Request $request, Document $document): JsonResponse
    {
        $this->authorize('delete', $document);
        DB::transaction(function () use ($request, $document): void {
            $this->audit->execute($document->organization_id, $request->user(), 'deleted', $document, $document->toArray(), null, $request);
            $document->delete();
        });

        return ApiResponse::noContent();
    }

    private function resolveEntity(?string $type, ?string $id, string $org): ?Model
    {
        return $this->entityLinks->resolveOptional($type, $id, $org);
    }
}
