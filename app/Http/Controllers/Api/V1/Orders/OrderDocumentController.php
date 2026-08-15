<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Controllers\Api\V1\Orders\Concerns\ResolvesOrderScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Documents\StoreDocumentRequest;
use App\Http\Resources\Api\V1\Documents\DocumentResource;
use App\Modules\Documents\Models\Document;
use App\Modules\Orders\Models\Order;
use App\Shared\Database\MorphMap;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Documents rattachés à une commande.
 *
 * Aucune table de fichiers propre aux commandes : le module Documents de la
 * Phase 1 est réutilisé tel quel, la liaison passe par `DocumentLink`.
 */
class OrderDocumentController extends Controller
{
    use ResolvesOrderScope;

    /**
     * Lister les documents d'une commande.
     *
     * Permission requise : `documents.view`.
     */
    public function index(ListRequest $request, Order $order): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->authorize('viewAny', [Document::class, $organizationId]);

        $documents = Document::where('organization_id', $organizationId)
            ->whereHas('links', fn ($query) => $query->where('entity_type', MorphMap::ORDER)->where('entity_id', $order->id))
            ->latest()
            ->paginate($request->getPerPage());

        return ApiResponse::paginated($documents->through(fn (Document $document) => new DocumentResource($document)));
    }

    /**
     * Téléverser un document et le rattacher à la commande.
     *
     * Permission requise : `documents.upload`. Mêmes contrôles que l'upload
     * générique : extensions, taille, type MIME déduit du contenu.
     */
    public function store(StoreDocumentRequest $request, Order $order): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->authorize('create', [Document::class, $organizationId]);

        $data = $request->validated();
        $file = $request->file('file');
        $path = $file->store("documents/$organizationId", 'local');

        try {
            $document = DB::transaction(function () use ($data, $file, $path, $organizationId, $order, $request): Document {
                $document = Document::create([
                    'organization_id' => $organizationId,
                    'reference_number' => $data['referenceNumber'] ?? null,
                    'document_type' => $data['documentType'],
                    'status' => $data['status'],
                    'file_name' => $file->getClientOriginalName(),
                    'storage_path' => $path,
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'size' => $file->getSize(),
                    'received_at' => $data['receivedAt'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                $document->links()->create(['entity_type' => MorphMap::ORDER, 'entity_id' => $order->id]);
                $this->audit($request, $organizationId, 'created', $document, null, $document->toArray());

                return $document;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return ApiResponse::created(new DocumentResource($document->load('links')));
    }
}
