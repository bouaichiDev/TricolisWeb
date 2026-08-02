<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Addresses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Addresses\StoreEntityAddressRequest;
use App\Http\Resources\Api\V1\Addresses\EntityAddressResource;
use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Shared\Database\EntityLinkResolver;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Liaisons génériques entre une adresse et les entités métier (EntityAddress).
 */
class AddressLinkController extends Controller
{
    public function __construct(private readonly EntityLinkResolver $entityLinks) {}

    /**
     * Lister les entités rattachées à une adresse.
     *
     * Permission requise : `addresses.view`.
     */
    public function index(Request $request, Address $address): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('view', $address);

        $links = EntityAddress::where('address_id', $address->id)
            ->where('organization_id', $organizationId)
            ->get();

        return ApiResponse::ok(EntityAddressResource::collection($links));
    }

    /**
     * Rattacher une adresse à une entité de l'organisation active.
     *
     * Permission requise : `addresses.create`.
     */
    public function store(StoreEntityAddressRequest $request, Address $address): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('view', $address);
        $this->authorize('create', [EntityAddress::class, $organizationId]);

        $data = $request->validated();
        $target = $this->entityLinks->resolve($data['entityType'], $data['entityId'], $organizationId);
        $addressType = $data['addressType'] ?? null;

        $exists = EntityAddress::where('address_id', $address->id)
            ->where('entity_type', $target['entity_type'])
            ->where('entity_id', $target['entity_id'])
            ->where('address_type', $addressType)
            ->exists();

        if ($exists) {
            return ApiResponse::error('Cette liaison existe déjà.', 409);
        }

        $link = EntityAddress::create([
            'organization_id' => $organizationId,
            'address_id' => $address->id,
            'entity_type' => $target['entity_type'],
            'entity_id' => $target['entity_id'],
            'address_type' => $addressType,
            'is_default' => $data['isDefault'] ?? false,
        ]);
        $this->audit($request, $organizationId, 'address_linked', $link, null, $link->toArray());

        return ApiResponse::created(new EntityAddressResource($link));
    }

    /**
     * Détacher une adresse d'une entité.
     *
     * Permission requise : `addresses.delete`. La dernière liaison ne peut pas
     * être retirée : elle rendrait l'adresse invisible dans son organisation.
     */
    public function destroy(Request $request, Address $address, EntityAddress $link): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('delete', $link);

        if ($link->address_id !== $address->id || $link->organization_id !== $organizationId) {
            return ApiResponse::error('Liaison introuvable pour cette adresse.', 404);
        }

        if (EntityAddress::where('address_id', $address->id)->count() === 1) {
            return ApiResponse::error('Impossible de retirer la dernière liaison d’une adresse.', 409);
        }

        $this->audit($request, $organizationId, 'address_unlinked', $link, $link->toArray(), null);
        $link->delete();

        return ApiResponse::noContent();
    }
}
