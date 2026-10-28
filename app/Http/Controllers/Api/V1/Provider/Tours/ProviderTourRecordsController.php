<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Tours;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Provider\Tours\RecordProofRequest;
use App\Http\Resources\Api\V1\Provider\ProviderProofResource;
use App\Modules\Documents\Models\Document;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\ProviderPortal\Actions\RecordProviderProofOfDelivery;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Modules\ProviderPortal\Services\ProviderDocumentAccess;
use App\Modules\ProviderPortal\Services\ProviderTourScope;
use App\Modules\ProviderPortal\Services\ProviderTourTimeline;
use App\Modules\Tours\Models\TourStop;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\AuditContext;
use Illuminate\Http\JsonResponse;

/**
 * Ce qu'une tournée a laissé derrière elle : sa chronologie, ses documents
 * d'exécution et les preuves de livraison de ses arrêts. Toujours derrière
 * ProviderTourScope : une tournée d'un autre fournisseur répond 404.
 */
class ProviderTourRecordsController extends Controller
{
    public function __construct(
        private readonly ProviderTourScope $scope,
        private readonly CurrentProviderContext $context,
    ) {}

    public function tracking(string $tour, ProviderTourTimeline $timeline): JsonResponse
    {
        return ApiResponse::ok($timeline->for($this->scope->tour($tour)));
    }

    public function documents(string $tour, ProviderDocumentAccess $documents): JsonResponse
    {
        $model = $this->scope->tour($tour);

        return ApiResponse::ok($documents->query($model->id)->latest()->limit(200)->get()->map(static fn (Document $document): array => [
            'id' => $document->id,
            'fileName' => $document->file_name,
            'documentType' => $document->document_type,
            'mimeType' => $document->mime_type,
            'size' => $document->size,
            'createdAt' => $document->created_at?->toIso8601String(),
        ])->values());
    }

    public function proofs(string $tour, string $stop): JsonResponse
    {
        $model = $this->stop($tour, $stop);

        return ApiResponse::ok(ProviderProofResource::collection(
            ProofOfDelivery::with('orderService:id,service_number')->where('tour_stop_id', $model->id)->orderBy('delivered_at')->get(),
        ));
    }

    public function storeProof(RecordProofRequest $request, string $tour, string $stop, RecordProviderProofOfDelivery $action): JsonResponse
    {
        $proof = $action->execute(
            $tour,
            $stop,
            $request->safe()->only(['orderServiceId', 'recipientName', 'remark']),
            $request->file('signature'),
            $request->file('photo'),
            new AuditContext($this->context->organizationId(), $this->context->user(), $request->ip()),
        );

        return ApiResponse::created(new ProviderProofResource($proof->load('orderService:id,service_number')));
    }

    private function stop(string $tour, string $stop): TourStop
    {
        return TourStop::where('tour_id', $this->scope->tour($tour)->id)->whereKey($stop)->firstOr(static fn () => abort(404, 'Arrêt introuvable.'));
    }
}
