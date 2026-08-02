<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Addresses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Addresses\StoreAddressRequest;
use App\Http\Requests\Api\V1\Addresses\UpdateAddressRequest;
use App\Http\Resources\Api\V1\Addresses\AddressResource;
use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Shared\Database\EntityLinkResolver;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    private const array MAPPING = ['code' => 'code', 'name' => 'name', 'address_line_1' => 'addressLine1', 'address_line_2' => 'addressLine2', 'address_line_3' => 'addressLine3', 'floor' => 'floor', 'address_number' => 'addressNumber', 'route' => 'route', 'sublocality' => 'sublocality', 'postal_code' => 'postalCode', 'city' => 'city', 'town' => 'town', 'country' => 'country', 'latitude' => 'latitude', 'longitude' => 'longitude', 'instructions' => 'instructions', 'time_window_from' => 'timeWindowFrom', 'time_window_to' => 'timeWindowTo', 'is_default' => 'isDefault', 'status' => 'status'];

    public function __construct(private readonly EntityLinkResolver $entityLinks) {}

    /**
     * Lister les adresses visibles dans l'organisation active.
     *
     * Permission requise : `addresses.view`. Une adresse est visible dès qu'une
     * liaison `EntityAddress` la rattache à l'organisation active. Recherche sur
     * `name`, `address_line_1`, `city`, `postal_code`.
     */
    public function index(ListRequest $request): JsonResponse
    {
        $org = $this->requireOrganizationId();
        $this->authorize('viewAny', [Address::class, $org]);
        $query = Address::whereHas('entityAddresses', fn ($q) => $q->where('organization_id', $org));
        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($q) => $q->where('name', 'like', "%$search%")->orWhere('address_line_1', 'like', "%$search%")->orWhere('city', 'like', "%$search%")->orWhere('postal_code', 'like', "%$search%"));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }
        $paginator = $query->orderBy($request->getSort('name', ['name', 'city', 'created_at']), $request->getDirection())->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (Address $address) => new AddressResource($address)));
    }

    /**
     * Créer une adresse et sa première liaison.
     *
     * Permission requise : `addresses.create`. `entityType` / `entityId` désignent
     * l'entité rattachée ; sans eux, l'adresse est liée à l'organisation active.
     * L'entité cible doit appartenir à l'organisation active, sinon 403.
     */
    public function store(StoreAddressRequest $request): JsonResponse
    {
        $org = $this->requireOrganizationId();
        $this->authorize('create', [Address::class, $org]);
        $data = $request->validated();
        $address = DB::transaction(function () use ($data, $org): Address {
            $address = Address::create(InputMapper::map($data, self::MAPPING));
            $link = $this->entityLinks->resolve($data['entityType'] ?? null, $data['entityId'] ?? null, $org);
            EntityAddress::create(['organization_id' => $org, 'address_id' => $address->id, 'entity_type' => $link['entity_type'], 'entity_id' => $link['entity_id'], 'address_type' => $data['addressType'] ?? null, 'is_default' => $data['isDefault'] ?? false]);

            return $address;
        });
        $this->audit($request, $org, 'created', $address, null, $address->toArray());

        return ApiResponse::created(new AddressResource($address));
    }

    /**
     * Consulter une adresse.
     *
     * Permission requise : `addresses.view`.
     */
    public function show(Request $request, Address $address): JsonResponse
    {
        $this->authorize('view', $address);

        return ApiResponse::ok(new AddressResource($address));
    }

    /**
     * Modifier une adresse.
     *
     * Permission requise : `addresses.update`.
     */
    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        $this->authorize('update', $address);
        $old = $address->toArray();
        $address->update(InputMapper::map($request->validated(), self::MAPPING));
        $this->audit($request, $this->requireOrganizationId(), 'updated', $address, $old, $address->fresh()->toArray());

        return ApiResponse::ok(new AddressResource($address->fresh()));
    }

    /**
     * Supprimer une adresse et toutes ses liaisons.
     *
     * Permission requise : `addresses.delete`.
     *
     * @response 204
     */
    public function destroy(Request $request, Address $address): JsonResponse
    {
        $this->authorize('delete', $address);
        $this->audit($request, $this->requireOrganizationId(), 'deleted', $address, $address->toArray(), null);
        DB::transaction(function () use ($address): void {
            $address->entityAddresses()->delete();
            $address->delete();
        });

        return ApiResponse::noContent();
    }
}
