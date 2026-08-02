<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Communications;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Communications\StoreCommunicationAttachmentRequest;
use App\Http\Resources\Api\V1\Communications\CommunicationAttachmentResource;
use App\Modules\Communications\Actions\ManageCommunicationAttachmentAction;
use App\Modules\Communications\DTOs\AddCommunicationAttachmentData;
use App\Modules\Communications\Exceptions\CommunicationNotEditable;
use App\Modules\Communications\Models\CommunicationAttachment;
use App\Modules\Communications\Models\OrderCommunication;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pièces jointes d'une communication.
 *
 * Aucune route `PATCH` : les deux snapshots sont figés à l'ajout, il n'y a rien
 * à modifier (§31).
 */
class CommunicationAttachmentController extends Controller
{
    use BuildsAuditContext;
    use ResolvesCommunicationScope;

    /**
     * Lister les pièces jointes d'une communication.
     *
     * Permission requise : `communication_attachments.view`.
     */
    public function index(Request $request, OrderCommunication $orderCommunication): JsonResponse
    {
        $organizationId = $this->guardCommunication($orderCommunication);
        $this->authorize('viewAny', [CommunicationAttachment::class, $organizationId]);

        $attachments = $orderCommunication->attachments()->with('document')->orderBy('created_at')->get();

        return ApiResponse::ok(CommunicationAttachmentResource::collection($attachments));
    }

    /**
     * Joindre un document à une communication.
     *
     * Permission requise : `communication_attachments.create`. Le document doit
     * relever de l'organisation active. Refusé en 409 passé le brouillon, et si
     * le document est déjà joint.
     */
    public function store(
        StoreCommunicationAttachmentRequest $request,
        OrderCommunication $orderCommunication,
        ManageCommunicationAttachmentAction $action,
    ): JsonResponse {
        $organizationId = $this->guardCommunication($orderCommunication);
        $this->authorize('create', [CommunicationAttachment::class, $organizationId]);

        try {
            $attachment = $action->add(
                $orderCommunication,
                AddCommunicationAttachmentData::fromValidated($request->validated()),
                $this->auditContext($request, $organizationId),
            );
        } catch (CommunicationNotEditable $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::created(new CommunicationAttachmentResource($attachment->load('document')));
    }

    /**
     * Consulter une pièce jointe.
     */
    public function show(
        Request $request,
        OrderCommunication $orderCommunication,
        CommunicationAttachment $attachment,
    ): JsonResponse {
        $this->guardAttachment($orderCommunication, $attachment);
        $this->authorize('view', $attachment);

        return ApiResponse::ok(new CommunicationAttachmentResource($attachment->load('document')));
    }

    /**
     * Retirer une pièce jointe.
     *
     * Permission requise : `communication_attachments.delete`. Le document
     * lui-même n'est jamais supprimé. Refusé en 409 passé le brouillon.
     *
     * @response 204
     */
    public function destroy(
        Request $request,
        OrderCommunication $orderCommunication,
        CommunicationAttachment $attachment,
        ManageCommunicationAttachmentAction $action,
    ): JsonResponse {
        $organizationId = $this->guardAttachment($orderCommunication, $attachment);
        $this->authorize('delete', $attachment);

        try {
            $action->remove($attachment, $this->auditContext($request, $organizationId));
        } catch (CommunicationNotEditable $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::noContent();
    }
}
