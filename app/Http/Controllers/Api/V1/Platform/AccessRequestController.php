<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\DecideAccessRequestRequest;
use App\Http\Requests\Api\V1\Platform\StoreAccessRequestRequest;
use App\Http\Resources\Api\V1\Platform\AccessRequestResource;
use App\Modules\Platform\Actions\ApproveAccessRequest;
use App\Modules\Platform\Actions\SubmitAccessRequest;
use App\Modules\Platform\Models\AccessRequest;
use App\Shared\Enums\AccessRequestStatus;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Les demandes d'accès : déposées par n'importe qui, tranchées par la plateforme.
 *
 * **Un seul contrôleur pour les deux moitiés**, parce que c'est une seule
 * ressource : le dépôt est public, tout le reste exige l'autorité plateforme.
 * Les séparer aurait fait deux contrôleurs pour une table, dont l'un se serait
 * appelé « public » — un nom qui décrit qui appelle, pas ce dont on parle.
 *
 * Ce que le dépôt **ne fait pas** est le cœur de l'affaire : ni compte, ni
 * organisation, ni jeton. C'est ce qui le distingue de `POST /auth/register`,
 * où trois champs suffisaient à obtenir un back-office.
 */
class AccessRequestController extends Controller
{
    /**
     * Déposer une demande d'accès.
     *
     * Endpoint public, limité par `throttle`. La réponse ne dit rien d'autre
     * que « c'est enregistré » : confirmer l'existence d'une adresse ou d'une
     * société renseignerait qui essaie.
     *
     * @response 201 array{data: array{message: string}, meta: array{}}
     */
    public function store(StoreAccessRequestRequest $request, SubmitAccessRequest $submit): JsonResponse
    {
        $submit->execute($request->validated());

        return ApiResponse::created([
            'message' => 'Votre demande a bien été transmise. Vous serez recontacté par courriel.',
        ]);
    }

    /**
     * Lister les demandes.
     *
     * Les plus récentes d'abord, filtrables par état : l'écran s'ouvre sur les
     * demandes en attente, qui sont les seules à appeler un geste.
     *
     * Permission requise : `organizations.view`, au niveau plateforme.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccessRequest::class);

        $status = $request->query('status');

        $requests = AccessRequest::query()
            ->when(
                is_string($status) && AccessRequestStatus::tryFrom($status) !== null,
                static fn ($query) => $query->where('status', $status),
            )
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->query('perPage', '25'), 100));

        return ApiResponse::paginated(
            $requests->through(fn (AccessRequest $row) => new AccessRequestResource($row)),
        );
    }

    /**
     * Accepter une demande : créer l'organisation et son administrateur.
     *
     * Permission requise : `organizations.create`, au niveau plateforme. Le
     * demandeur reçoit un lien pour choisir son mot de passe — jamais un mot de
     * passe en clair.
     */
    public function approve(
        DecideAccessRequestRequest $request,
        AccessRequest $accessRequest,
        ApproveAccessRequest $approve,
    ): JsonResponse {
        $this->authorize('decide', AccessRequest::class);

        $decided = $approve->execute($accessRequest, $request->user());

        if (is_string($request->validated('note'))) {
            $decided->update(['decision_note' => $request->validated('note')]);
        }

        $this->audit(
            $request,
            (string) $decided->organization_id,
            'access_request_approved',
            $decided,
            null,
            ['email' => $decided->email],
        );

        return ApiResponse::resource(new AccessRequestResource($decided->refresh()));
    }

    /**
     * Refuser une demande.
     *
     * Rien n'est créé, et la ligne reste : c'est elle qui empêche de reposer la
     * même question indéfiniment, et qui garde le motif du refus.
     */
    public function reject(DecideAccessRequestRequest $request, AccessRequest $accessRequest): JsonResponse
    {
        $this->authorize('decide', AccessRequest::class);

        abort_if($accessRequest->status->isDecided(), 422, 'Cette demande a déjà été tranchée.');

        $accessRequest->update([
            'status' => AccessRequestStatus::REJECTED,
            'decision_note' => $request->validated('note'),
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return ApiResponse::resource(new AccessRequestResource($accessRequest->refresh()));
    }
}
