<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contacts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Contacts\ListContactRequest;
use App\Http\Requests\Api\V1\Contacts\StoreContactRequest;
use App\Http\Requests\Api\V1\Contacts\UpdateContactRequest;
use App\Http\Resources\Api\V1\Contacts\ContactResource;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\EntityContact;
use App\Shared\Database\EntityLinkResolver;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contacts partagés, rattachés aux entités métier via `EntityContact`.
 */
class ContactController extends Controller
{
    private const array MAPPING = ['first_name' => 'firstName', 'last_name' => 'lastName', 'phone' => 'phone', 'mobile' => 'mobile', 'email' => 'email', 'preferred_language' => 'preferredLanguage', 'is_active' => 'isActive'];

    public function __construct(private readonly EntityLinkResolver $entityLinks) {}

    /**
     * Lister les contacts visibles dans l'organisation active.
     *
     * Permission requise : `contacts.view`. Un contact est visible dès qu'une
     * liaison `EntityContact` le rattache à l'organisation active. Recherche sur
     * le nom, le prénom, l'email et le téléphone ; filtre `isActive`.
     */
    public function index(ListContactRequest $request): JsonResponse
    {
        $org = $this->requireOrganizationId();
        $this->authorize('viewAny', [Contact::class, $org]);
        $query = Contact::whereHas('entityContacts', fn ($q) => $q->where('organization_id', $org));

        if ($request->hasEntityFilter()) {
            $entityType = $request->validated('entityType');
            $entityId = $request->validated('entityId');

            // Les liaisons sont chargées en même temps : elles portent le rôle
            // du contact et le drapeau principal, que le contact lui-même ignore.
            $scope = fn ($q) => $q->where('organization_id', $org)
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId);

            $query->whereHas('entityContacts', $scope)->with(['entityContacts' => $scope]);
        }
        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($q) => $q->where('first_name', 'like', "%$search%")->orWhere('last_name', 'like', "%$search%")->orWhere('email', 'like', "%$search%")->orWhere('phone', 'like', "%$search%"));
        }
        if ($request->filled('isActive')) {
            $query->where('is_active', $request->boolean('isActive'));
        }
        $paginator = $query->orderBy($request->getSort('last_name', ['first_name', 'last_name', 'created_at']), $request->getDirection())->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (Contact $contact) => new ContactResource($contact)));
    }

    /**
     * Créer un contact et sa première liaison.
     *
     * Permission requise : `contacts.create`. `entityType` / `entityId` désignent
     * l'entité rattachée ; sans eux, le contact est lié à l'organisation active.
     */
    public function store(StoreContactRequest $request): JsonResponse
    {
        $org = $this->requireOrganizationId();
        $this->authorize('create', [Contact::class, $org]);
        $data = $request->validated();
        $contact = DB::transaction(function () use ($data, $org): Contact {
            $contact = Contact::create(InputMapper::map($data, self::MAPPING));
            $link = $this->entityLinks->resolve($data['entityType'] ?? null, $data['entityId'] ?? null, $org);
            EntityContact::create(['organization_id' => $org, 'contact_id' => $contact->id, 'entity_type' => $link['entity_type'], 'entity_id' => $link['entity_id'], 'contact_role' => $data['contactRole'] ?? 'other', 'is_primary' => $data['isPrimary'] ?? false, 'notify_by_email' => $data['notifyByEmail'] ?? false, 'notify_by_sms' => $data['notifyBySms'] ?? false]);

            return $contact;
        });
        $this->audit($request, $org, 'created', $contact, null, $contact->toArray());

        return ApiResponse::created(new ContactResource($contact));
    }

    /**
     * Consulter un contact.
     *
     * Permission requise : `contacts.view`.
     */
    public function show(Request $request, Contact $contact): JsonResponse
    {
        $this->authorize('view', $contact);

        return ApiResponse::ok(new ContactResource($contact));
    }

    /**
     * Modifier un contact.
     *
     * Permission requise : `contacts.update`.
     */
    public function update(UpdateContactRequest $request, Contact $contact): JsonResponse
    {
        $this->authorize('update', $contact);
        $old = $contact->toArray();
        $contact->update(InputMapper::map($request->validated(), self::MAPPING));
        $this->audit($request, $this->requireOrganizationId(), 'updated', $contact, $old, $contact->fresh()->toArray());

        return ApiResponse::ok(new ContactResource($contact->fresh()));
    }

    /**
     * Supprimer un contact et toutes ses liaisons.
     *
     * Permission requise : `contacts.delete`.
     *
     * @response 204
     */
    public function destroy(Request $request, Contact $contact): JsonResponse
    {
        $this->authorize('delete', $contact);
        $this->audit($request, $this->requireOrganizationId(), 'deleted', $contact, $contact->toArray(), null);
        DB::transaction(function () use ($contact): void {
            $contact->entityContacts()->delete();
            $contact->delete();
        });

        return ApiResponse::noContent();
    }
}
