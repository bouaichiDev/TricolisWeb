<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Organizations\StoreOrganizationRequest;
use App\Http\Requests\Api\V1\Organizations\UpdateOrganizationRequest;
use App\Http\Resources\Api\V1\Organizations\OrganizationResource;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PlatformAccess;
use App\Modules\Organizations\Actions\SyncOrganizationMenu;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Enums\OrganizationStatus;
use App\Shared\Enums\UserStatus;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Organisations auxquelles l'utilisateur connecté est rattaché.
 *
 * Ces routes ne demandent pas l'en-tête `X-Organization-Id` : elles servent
 * justement à choisir l'organisation active.
 */
class OrganizationController extends Controller
{
    public function __construct(private readonly PlatformAccess $platform) {}

    /**
     * Lister les organisations.
     *
     * Deux portées, et une seule requête : un administrateur plateforme les voit
     * toutes, tout autre utilisateur ne voit que celles dont il est membre.
     *
     * Le bornage est fait ici, pas dans la policy : `viewAny` doit rester
     * autorisé pour tous, cette liste servant précisément à choisir son
     * organisation active.
     *
     * Recherche sur `name` et `code`, filtre `status`.
     */
    public function index(ListRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Organization::class);

        /** @var User $user */
        $user = $request->user();

        $query = Organization::query();

        if (! $this->platform->isPlatformAdmin($user)) {
            $query->whereHas('organizationUsers', static function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $sort = $request->getSort('name', ['name', 'code', 'created_at']);
        $direction = $request->getDirection();

        $paginator = $query->orderBy($sort, $direction)->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (Organization $organization): OrganizationResource => new OrganizationResource($organization)));
    }

    /**
     * Créer une organisation.
     *
     * L'auteur devient automatiquement propriétaire et membre principal, dans
     * la même transaction que la création de l'organisation.
     */
    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $this->authorize('create', Organization::class);

        $validated = $request->validated();

        $organization = DB::transaction(static function () use ($validated, $request): Organization {
            $organization = Organization::create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'legal_name' => $validated['legalName'] ?? null,
                'registration_number' => $validated['registrationNumber'] ?? null,
                'tax_number' => $validated['taxNumber'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'preferred_language' => $validated['preferredLanguage'] ?? 'fr',
                'timezone' => $validated['timezone'] ?? 'Europe/Paris',
                'currency_code' => $validated['currencyCode'] ?? 'EUR',
                'status' => $validated['status'] ?? OrganizationStatus::PENDING->value,
                'settings' => $validated['settings'] ?? [],
            ]);

            /** @var User $user */
            $user = $request->user();

            OrganizationUser::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'is_owner' => true,
                'is_primary' => true,
                'status' => UserStatus::ACTIVE,
                'joined_at' => now(),
            ]);

            // Le menu de base est posé dans la même transaction : une
            // organisation créée sans lui n'aurait rien à montrer dans son
            // écran de réglage, et son administrateur ne saurait pas quelles
            // entrées existent.
            app(SyncOrganizationMenu::class)->execute($organization->id);

            return $organization;
        });

        $this->audit($request, $organization->id, 'created', $organization, null, $organization->toArray());

        return ApiResponse::created(new OrganizationResource($organization));
    }

    /**
     * Consulter une organisation.
     *
     * L'utilisateur doit en être membre, sinon 403.
     */
    public function show(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        return ApiResponse::ok(new OrganizationResource($organization));
    }

    /**
     * Modifier une organisation.
     *
     * Permission requise : `organizations.update`, ou être propriétaire.
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);
        $oldValues = $organization->toArray();

        $validated = $request->validated();

        $data = [];

        foreach ([
            'code' => 'code',
            'name' => 'name',
            'legal_name' => 'legalName',
            'registration_number' => 'registrationNumber',
            'tax_number' => 'taxNumber',
            'email' => 'email',
            'phone' => 'phone',
            'preferred_language' => 'preferredLanguage',
            'timezone' => 'timezone',
            'currency_code' => 'currencyCode',
            'status' => 'status',
            'settings' => 'settings',
        ] as $db => $input) {
            if (array_key_exists($input, $validated)) {
                $data[$db] = $validated[$input];
            }
        }

        $organization->update($data);
        $this->audit($request, $organization->id, 'updated', $organization, $oldValues, $organization->fresh()->toArray());

        return ApiResponse::ok(new OrganizationResource($organization->fresh()));
    }

    /**
     * Supprimer une organisation.
     *
     * Réservé aux propriétaires de l'organisation.
     *
     * @response 204
     */
    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('delete', $organization);
        $this->audit($request, $organization->id, 'deleted', $organization, $organization->toArray(), null);
        $organization->delete();

        return ApiResponse::noContent();
    }
}
