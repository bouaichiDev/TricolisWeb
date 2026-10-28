<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Provider\ProviderSettlementResource;
use App\Modules\CustomerPortal\Services\Documents\CustomerDocumentFiles;
use App\Modules\Documents\Models\Document;
use App\Modules\ProviderPortal\Services\ProviderSettlementScope;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Décomptes du fournisseur courant, en lecture seule : aucune route ne
 * calcule, valide, modifie ni supprime un décompte depuis le portail.
 */
class ProviderSettlementController extends Controller
{
    public function __construct(private readonly ProviderSettlementScope $scope) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('perPage', '25')));
        $settlements = $this->scope->settlements()->orderByDesc('period_to')->orderByDesc('settlement_number')->paginate($perPage);

        return ApiResponse::paginated($settlements->through(static fn (ProviderSettlement $settlement) => (new ProviderSettlementResource($settlement))->resolve()));
    }

    public function show(string $settlement): JsonResponse
    {
        $model = $this->scope->settlement($settlement)->load(['lines' => static fn ($query) => $query->orderBy('id'), 'lines.orderService:id,service_number']);

        return ApiResponse::ok(new ProviderSettlementResource($model));
    }

    public function documents(string $settlement): JsonResponse
    {
        $documents = $this->scope->documents($this->scope->settlement($settlement))->latest()->limit(100)->get();

        return ApiResponse::ok($documents->map(static fn (Document $document): array => [
            'id' => $document->id,
            'fileName' => $document->file_name,
            'documentType' => $document->document_type,
            'mimeType' => $document->mime_type,
            'size' => $document->size,
            'createdAt' => $document->created_at?->toIso8601String(),
        ])->values());
    }

    public function download(string $settlement, string $document): StreamedResponse
    {
        $file = $this->scope->documents($this->scope->settlement($settlement))->whereKey($document)->firstOr(static fn () => abort(404, 'Document introuvable.'));

        return CustomerDocumentFiles::download($file);
    }
}
