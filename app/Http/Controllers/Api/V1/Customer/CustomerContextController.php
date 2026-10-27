<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\UpdateProfileRequest;
use App\Http\Resources\Api\V1\Customer\CustomerContextResource;
use App\Http\Resources\Api\V1\Customer\CustomerPortalRoleResource;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\CustomerPortal\Services\CustomerRoleGuard;
use App\Modules\Identity\Actions\UpdateUserProfile;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Contexte, profil, permissions et rôles du membre connecté.
 */
class CustomerContextController extends Controller
{
    public function __construct(private readonly CurrentCustomerContext $context) {}

    /**
     * Le Customer courant, le membre, ses rôles et permissions effectives.
     */
    public function context(): JsonResponse
    {
        return ApiResponse::ok(new CustomerContextResource($this->context));
    }

    /**
     * Les permissions effectives, seules.
     */
    public function permissions(): JsonResponse
    {
        return ApiResponse::ok(['permissions' => $this->context->permissions()]);
    }

    /**
     * Les rôles attribuables dans ce portail. Permission : `customer.roles.view`.
     */
    public function roles(): JsonResponse
    {
        return ApiResponse::ok(
            CustomerPortalRoleResource::collection(CustomerRoleGuard::rolesOf($this->context->customer()))
        );
    }

    /**
     * Le profil du compte connecté.
     */
    public function profile(): JsonResponse
    {
        return ApiResponse::ok((new CustomerContextResource($this->context))->resolve()['user']);
    }

    /**
     * Modifier son propre profil : nom, téléphone, langue. L'email, identifiant
     * de connexion, ne se change pas ici.
     */
    public function updateProfile(UpdateProfileRequest $request, UpdateUserProfile $update, CustomerPortalAudit $audit): JsonResponse
    {
        $user = $this->context->user();
        $changes = $update->execute($user, $request->validated());

        if ($changes['old'] !== $changes['new']) {
            $audit->record($this->context->customer(), $user, 'profile_updated', $this->context->membership(), $changes['old'], $changes['new'], $request->ip());
        }

        return $this->profile();
    }
}
