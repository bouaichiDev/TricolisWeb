<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Claims;

use App\Http\Controllers\Api\V1\Customer\Concerns\ResolvesCustomerClaim;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Claims\ListCustomerClaimsRequest;
use App\Http\Requests\Api\V1\Customer\Claims\StoreCustomerClaimRequest;
use App\Http\Requests\Api\V1\Customer\Claims\UpdateCustomerClaimRequest;
use App\Http\Resources\Api\V1\Customer\Claims\CustomerClaimListResource;
use App\Http\Resources\Api\V1\Customer\Claims\CustomerClaimResource;
use App\Modules\Claims\Models\Claim;
use App\Modules\CustomerPortal\Actions\CreateCustomerClaim;
use App\Modules\CustomerPortal\Actions\UpdateCustomerClaim;
use App\Modules\CustomerPortal\Queries\CustomerClaimListQuery;
use App\Modules\CustomerPortal\Services\Claims\CustomerClaimCapabilities;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Réclamations du Customer courant (§6-§11).
 *
 * Authentification → Customer Context → permission (`customer.claims.*`,
 * sur la route) → appartenance (`claim()` : 404 hors du client) → règle
 * (`CustomerClaimCapabilities`, revérifiée par les actions).
 */
class CustomerClaimController extends Controller
{
    use ResolvesCustomerClaim;

    private const array DETAIL = ['order:id,order_number,customer_reference', 'orderService:id,order_id,service_id,service_number', 'orderService.service:id,name'];

    public function __construct(
        private readonly CurrentCustomerContext $context,
        private readonly CustomerClaimCapabilities $capabilities,
    ) {}

    /** Permission : `customer.claims.view`. */
    public function index(ListCustomerClaimsRequest $request, CustomerClaimListQuery $query): JsonResponse
    {
        $page = $query->paginate($this->context->customer(), $request->validated());

        return ApiResponse::paginated($page->through(static fn (Claim $claim) => new CustomerClaimListResource($claim)));
    }

    /** Permission : `customer.claims.view`. */
    public function show(string $claim): JsonResponse
    {
        return $this->detail($this->claim($this->context, $claim));
    }

    /** Permission : `customer.claims.create`. */
    public function store(StoreCustomerClaimRequest $request, CreateCustomerClaim $create): JsonResponse
    {
        return $this->detail($create->execute($request->validated(), $request), 201);
    }

    /** Permission : `customer.claims.update`. 409 `CLAIM_NOT_EDITABLE` une fois clôturée. */
    public function update(UpdateCustomerClaimRequest $request, string $claim, UpdateCustomerClaim $update): JsonResponse
    {
        return $this->detail($update->execute($this->claim($this->context, $claim), $request->validated(), $request));
    }

    private function detail(Claim $claim, int $status = 200): JsonResponse
    {
        $claim->load(self::DETAIL);
        $resource = new CustomerClaimResource($claim, $this->capabilities->for($claim));

        return $status === 201 ? ApiResponse::created($resource) : ApiResponse::ok($resource);
    }
}
