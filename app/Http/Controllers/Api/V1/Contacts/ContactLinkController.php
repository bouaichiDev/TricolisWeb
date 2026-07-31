<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contacts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Contacts\StoreEntityContactRequest;
use App\Http\Resources\Api\V1\Contacts\EntityContactResource;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\EntityContact;
use App\Shared\Database\EntityLinkResolver;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Liaisons génériques entre un contact et les entités métier (EntityContact).
 */
class ContactLinkController extends Controller
{
    public function __construct(private readonly EntityLinkResolver $entityLinks) {}

    /**
     * Lister les entités rattachées à un contact.
     *
     * Permission requise : `contacts.view`.
     */
    public function index(Request $request, Contact $contact): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('view', $contact);

        $links = EntityContact::where('contact_id', $contact->id)
            ->where('organization_id', $organizationId)
            ->get();

        return ApiResponse::ok(EntityContactResource::collection($links));
    }

    /**
     * Rattacher un contact à une entité de l'organisation active.
     *
     * Permission requise : `contacts.create`.
     */
    public function store(StoreEntityContactRequest $request, Contact $contact): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('view', $contact);
        $this->authorize('create', [EntityContact::class, $organizationId]);

        $data = $request->validated();
        $target = $this->entityLinks->resolve($data['entityType'], $data['entityId'], $organizationId);
        $role = $data['contactRole'] ?? 'other';

        $exists = EntityContact::where('contact_id', $contact->id)
            ->where('entity_type', $target['entity_type'])
            ->where('entity_id', $target['entity_id'])
            ->where('contact_role', $role)
            ->exists();

        if ($exists) {
            return ApiResponse::error('Cette liaison existe déjà.', 409);
        }

        $link = EntityContact::create([
            'organization_id' => $organizationId,
            'contact_id' => $contact->id,
            'entity_type' => $target['entity_type'],
            'entity_id' => $target['entity_id'],
            'contact_role' => $role,
            'is_primary' => $data['isPrimary'] ?? false,
            'notify_by_email' => $data['notifyByEmail'] ?? false,
            'notify_by_sms' => $data['notifyBySms'] ?? false,
        ]);
        $this->audit($request, $organizationId, 'contact_linked', $link, null, $link->toArray());

        return ApiResponse::created(new EntityContactResource($link));
    }

    /**
     * Détacher un contact d'une entité.
     *
     * Permission requise : `contacts.delete`. La dernière liaison ne peut pas
     * être retirée : elle rendrait le contact invisible dans son organisation.
     */
    public function destroy(Request $request, Contact $contact, EntityContact $link): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('delete', $link);

        if ($link->contact_id !== $contact->id || $link->organization_id !== $organizationId) {
            return ApiResponse::error('Liaison introuvable pour ce contact.', 404);
        }

        if (EntityContact::where('contact_id', $contact->id)->count() === 1) {
            return ApiResponse::error('Impossible de retirer la dernière liaison d’un contact.', 409);
        }

        $this->audit($request, $organizationId, 'contact_unlinked', $link, $link->toArray(), null);
        $link->delete();

        return ApiResponse::noContent();
    }
}
