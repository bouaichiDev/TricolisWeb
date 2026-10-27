<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\InviteCustomerUserRequest;
use App\Http\Resources\Api\V1\Customer\CustomerPortalUserResource;
use App\Modules\CustomerPortal\Actions\InviteCustomerUser;
use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Services\CustomerRoleGuard;
use App\Modules\CustomerPortal\Support\CustomerPortalRoles;
use App\Modules\Customers\Models\Customer;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Le portail d'un client, vu depuis l'administration du transporteur.
 *
 * C'est par ici que naît le **premier** compte d'un client : personne, chez
 * IKEA, ne peut s'inviter soi-même. Le transporteur désigne le propriétaire du
 * portail — rôle `CUSTOMER_ADMIN` par défaut — qui invitera ensuite ses
 * collègues depuis le portail.
 *
 * Permission : `customers.update` sur la fiche du client.
 */
class CustomerPortalUserController extends Controller
{
    /**
     * Lister les membres du portail d'un client.
     */
    public function index(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $members = CustomerUser::forCustomer($customer->id)->with(['user', 'roles'])->latest()->get();

        return ApiResponse::ok(CustomerPortalUserResource::collection($members));
    }

    /**
     * Inviter un membre, propriétaire du portail s'il est le premier.
     *
     * `roleId` désigne un rôle de portail du transporteur ; à défaut,
     * `CUSTOMER_ADMIN`. Le transporteur n'est pas soumis au plafond de
     * délégation du portail : c'est lui qui en fixe les rôles.
     */
    public function invite(InviteCustomerUserRequest $request, Customer $customer, InviteCustomerUser $invite): JsonResponse
    {
        $this->authorize('update', $customer);

        $data = $request->validated();
        $roles = CustomerRoleGuard::rolesOf($customer);
        $role = empty($data['roleId'])
            ? $roles->firstWhere('code', CustomerPortalRoles::ADMIN)
            : $roles->firstWhere('id', $data['roleId']);

        if ($role === null) {
            throw ValidationException::withMessages(['roleId' => ['Ce rôle n’existe pas pour le portail client.']]);
        }

        $isFirst = ! CustomerUser::forCustomer($customer->id)->exists();

        $member = $invite->execute($customer, $data, $role, $request->user(), $isFirst, $request->ip());

        return ApiResponse::created(new CustomerPortalUserResource($member));
    }
}
