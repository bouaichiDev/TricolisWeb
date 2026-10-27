<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Customer\Concerns\ResolvesCustomerMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\InviteCustomerUserRequest;
use App\Http\Requests\Api\V1\Customer\ListCustomerUsersRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerUserRequest;
use App\Http\Resources\Api\V1\Customer\CustomerPortalUserResource;
use App\Modules\CustomerPortal\Actions\InviteCustomerUser;
use App\Modules\CustomerPortal\Actions\UpdateCustomerUser;
use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPermissions;
use App\Modules\CustomerPortal\Services\CustomerRoleGuard;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Utilisateurs du portail d'un client, administrés par ce client.
 *
 * Toute requête est bornée au Customer courant : la liste n'en sort jamais,
 * et un membre d'un autre client est introuvable.
 */
class CustomerUserController extends Controller
{
    use ResolvesCustomerMember;

    public function __construct(private readonly CurrentCustomerContext $context) {}

    /**
     * Lister les membres du portail. Permission : `customer.users.view`.
     */
    public function index(ListCustomerUsersRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $query = CustomerUser::forCustomer($this->context->customerId())
            ->with(['user', 'roles'])
            ->join('users', 'users.id', '=', 'customer_users.user_id')
            ->select('customer_users.*')
            ->orderBy('users.last_name')
            ->orderBy('users.first_name');

        if (! empty($filters['status'])) {
            $query->where('customer_users.status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(static fn ($builder) => $builder
                ->where('users.email', 'like', $search)
                ->orWhere('users.first_name', 'like', $search)
                ->orWhere('users.last_name', 'like', $search));
        }

        return ApiResponse::paginated(
            $query->paginate((int) ($filters['perPage'] ?? 25))
                ->through(static fn (CustomerUser $member) => new CustomerPortalUserResource($member))
        );
    }

    /**
     * Consulter un membre. Permission : `customer.users.view`.
     */
    public function show(string $id): JsonResponse
    {
        return ApiResponse::ok(new CustomerPortalUserResource($this->member($this->context, $id)));
    }

    /**
     * Inviter une adresse. Permission : `customer.users.invite`, et
     * `customer.users.assign_role` pour fournir `roleId`.
     */
    public function invite(InviteCustomerUserRequest $request, InviteCustomerUser $invite): JsonResponse
    {
        $data = $request->validated();
        $role = null;

        if (! empty($data['roleId'])) {
            if (! $this->context->can('customer.users.assign_role')) {
                throw ValidationException::withMessages(['roleId' => ['Vous ne pouvez pas attribuer de rôle.']]);
            }

            $role = CustomerRoleGuard::resolve(
                $this->context->customer(),
                [$data['roleId']],
                CustomerPermissions::delegableFor($this->context->membership()),
                'roleId',
            )->first();
        }

        $member = $invite->execute(
            $this->context->customer(),
            $data,
            $role,
            $this->context->user(),
            false,
            $request->ip(),
        );

        return ApiResponse::created(new CustomerPortalUserResource($member));
    }

    /**
     * Corriger l'identité d'un membre. Permission : `customer.users.update`.
     */
    public function update(UpdateCustomerUserRequest $request, string $id, UpdateCustomerUser $update): JsonResponse
    {
        $member = $update->execute($this->context->membership(), $this->member($this->context, $id), $request->validated(), $request->ip());

        return ApiResponse::ok(new CustomerPortalUserResource($member));
    }
}
