<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Customer\Concerns\ResolvesCustomerMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\AssignCustomerUserRolesRequest;
use App\Http\Resources\Api\V1\Customer\CustomerPortalUserResource;
use App\Modules\CustomerPortal\Actions\AssignCustomerUserRoles;
use App\Modules\CustomerPortal\Actions\ChangeCustomerUserStatus;
use App\Modules\CustomerPortal\Actions\ResendCustomerInvitation;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ce qui ouvre ou ferme l'accès d'un membre : statut, invitation, rôles.
 */
class CustomerUserAccessController extends Controller
{
    use ResolvesCustomerMember;

    public function __construct(private readonly CurrentCustomerContext $context) {}

    /**
     * Réactiver un membre. Permission : `customer.users.disable`.
     */
    public function enable(Request $request, string $id, ChangeCustomerUserStatus $status): JsonResponse
    {
        $member = $status->enable($this->context->membership(), $this->member($this->context, $id), $request->ip());

        return ApiResponse::ok(new CustomerPortalUserResource($member));
    }

    /**
     * Désactiver un membre ; ses jetons de portail sont révoqués.
     * Permission : `customer.users.disable`.
     */
    public function disable(Request $request, string $id, ChangeCustomerUserStatus $status): JsonResponse
    {
        $member = $status->disable($this->context->membership(), $this->member($this->context, $id), $request->ip());

        return ApiResponse::ok(new CustomerPortalUserResource($member));
    }

    /**
     * Renvoyer l'invitation d'un membre pas encore activé.
     * Permission : `customer.users.invite`.
     */
    public function resendInvitation(Request $request, string $id, ResendCustomerInvitation $resend): JsonResponse
    {
        $member = $resend->execute($this->member($this->context, $id), $this->context->user(), $request->ip());

        return ApiResponse::ok(new CustomerPortalUserResource($member));
    }

    /**
     * Remplacer les rôles d'un membre. Permission : `customer.users.assign_role`.
     */
    public function roles(AssignCustomerUserRolesRequest $request, string $id, AssignCustomerUserRoles $assign): JsonResponse
    {
        $member = $assign->execute(
            $this->context->membership(),
            $this->member($this->context, $id),
            $request->validated('roleIds'),
            $request->ip(),
        );

        return ApiResponse::ok(new CustomerPortalUserResource($member));
    }
}
